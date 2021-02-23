<?php

namespace FreePBX\modules\Queues\Api\Rest;

use FreePBX\modules\Api\Rest\Base;

class Queues extends Base {
    protected $module = 'queues';

    public function __construct($freepbx, $module) {
        parent::__construct($freepbx, $module);
        $this->freepbx->Modules->loadFunctionsInc($module);
    }

    public function setupRoutes($app) {
        /**
         * @verb    GET
         * @returns - the queue list
         * @uri     /queues
         */
        $app->get('/', function($request, $response, $args) {
            $list = [];
            $queues = queues_list();

            foreach ($queues as $queue) {
                $entry = new \stdClass();
                $entry->extension = $queue[0];
                $entry->name = $queue[1];
                $list[$queue[0]] = $entry;
            }

            return $response->withJson(!empty($list) ? $list : false);
        })->add($this->checkAllReadScopeMiddleware());

        /**
         * @verb    GET
         * @returns - a list of queues with their members (static and dynamic)
         * @uri     /queues/members
         */
        $app->get('/members', function($request, $response, $args) {
            $list = [];

            // Get dynamic members priority from astdb
            $get = $this->freepbx->astman->database_show('QPENALTY');
            if ($get) {
                foreach ($get as $key => $value) {
                    $keys = explode('/', $key);
                    if (isset($keys[3]) && $keys[3] == 'agents') {
                        $queue = $keys[2];
                        $dynmember = $keys[4];

                        $list[$queue]['dynmembers'][] = $dynmember;
                    }
                }
            }

            // Get static members
            $allmembers = queues_get_static_members();
            foreach ($allmembers as $queue => $members) {
                foreach ($members as $member) {
                    if (preg_match("/^.*?\/([\d]+).*,([\d]+)$/", $member, $matches)) {
                        $list[$queue]['members'][] = $matches[1];
                    }
                }
            }

            return $response->withJson(!empty($list) ? $list : false);
        })->add($this->checkAllReadScopeMiddleware());

        /**
         * @verb    GET
         * @returns - a list of queues with their members (static and dynamic)
         * @uri     /queues/members
         */
        $app->get('/members/{id}', function($request, $response, $args) {
            $queue = queues_get($args['id']);
            if (!$queue) {
                return $response->withJson(false);
            }

            $queue_members = ['dynmembers' => [],
                              'member' => []];

            // fill dynmembers
            if (!empty($queue['dynmembers'])) {
                foreach (explode("\n", $queue['dynmembers']) as $member) {
                    $queue_members['dynmembers'][] = substr($member, 0, strpos($member, ","));
                }
            }

            // fill static members
            if (!empty($queue['member'])) {
                foreach ($queue['member'] as $member) {
                    if (preg_match("/^.*?\/([\d]+).*,([\d]+)$/", $member, $matches)) {
                        $queue_members['member'][] = $matches[1];
                    }
                }
            }

            return $response->withJson($queue_members);
        })->add($this->checkAllReadScopeMiddleware());

        /**
         * @verb    PUT
         * @returns - the result of setting queues members
         * @uri     /queues/members/:id
         */
        $app->put('/members/{id}', function($request, $response, $args) {
            // Get queue
            $queue = queues_get($args['id']);
            if (empty($queue)) {
                return $response->withJson(false);
            }

            $params = $request->getParsedBody();

            // Save static members
            if (isset($params['member'])) {
                $count = 0;
                $members = explode("\n", $params['member']);
 			
                // parse and transform members
                foreach (array_keys($members) as $key) {
                    //trim it
                    $members[$key] = trim($members[$key]);

                    // check if an agent (starts with a or A)
                    $exten_prefix = strtoupper(substr($members[$key], 0, 1));
                    $this_member = preg_replace("/[^0-9#\,*]/", "", $members[$key]);
                    switch ($exten_prefix) {
                        case 'A':
                            $exten_type = 'Agent';
                            break;
                        case 'P':
                            $exten_type = 'PJSIP';
                            break;
                        case 'S':
                            $exten_type = 'SIP';
                            break;
                        case 'X':
                            $exten_type = 'IAX2';
                            break;
                        case 'Z':
                            $exten_type = 'ZAP';
                            break;
                        case 'D':
                            $exten_type = 'DAHDI';
                            break;
                        default;
                            $exten_type = 'Local';
                    }

                    $penalty_pos = strrpos($this_member, ",");
                    if ($penalty_pos === false) {
                        $penalty_val = 0;
                    } else {
                        $penalty_val = substr($this_member, $penalty_pos + 1); // get penalty
                        $this_member = substr($this_member, 0, $penalty_pos); // clean up ext
                        $this_member = preg_replace("/[^0-9#*]/", "",
                                                    $this_member); //clean out other ,'s
                        $penalty_val = preg_replace("/[^0-9*]/", "",
                                                    $penalty_val); // get rid of #'s if there
                        $penalty_val = ($penalty_val == "") ? 0 : $penalty_val;
                    }

                    // remove blanks // prefix with the channel
                    if (empty($this_member)) {
                        unset($members[$key]);
                    } else {
                        switch ($exten_type) {
                            case 'Agent':
                            case 'SIP':
                            case 'IAX2':
                            case 'PJSIP':
                            case 'ZAP':
                            case 'DAHDI':
                                $members[$key] = "$exten_type/$this_member,$penalty_val";
                                break;
                            case 'Local':
                                $members[$key] = "$exten_type/$this_member@from-queue/n,$penalty_val";
                        }
                    }
                }
                $members = array_unique($members);

                // Prepare DB data
                $fields = [];
                $count = 0;
                foreach ($members as $member) {
                    $fields[] = [$args['id'],
                                 'member',
                                 $member,
                                 $count];
                    $count++;
                }

                // Update to DB
                $this->freepbx->Database->query(sprintf("DELETE FROM queues_details WHERE id = '%s' AND keyword='member'",
                                                        $args['id']));
                $compiled = $this->freepbx->Database->prepare('INSERT INTO queues_details (id, keyword, data, flags) values (?,?,?,?)');
                array_walk($fields, function($field) use ($compiled) {
                    $compiled->execute($field);
                });
            }
            // Save dynamic members
            if (isset($params['dynmembers'])) {
                $params['dynmembers'] = explode("\n", $params['dynmembers']);
                $params['dynmembers'] = array_unique($params['dynmembers']);

                // Get running dynmemberonly
                $dynmemberonly = $this->freepbx->astman->database_get(sprintf('QPENALTY/%d',
                                                                              $args['id']),
                                                                      'dynmemberonly');

                // Restore dyn agents
                $this->freepbx->astman->database_deltree(sprintf('QPENALTY/%d', $args['id']));

                // Set dyn agents
                foreach ($params['dynmembers'] as $member) {
                    $mem = explode(',', $member);
                    if (isset($mem[0]) && trim($mem[0]) != '') {
                        $penalty = isset($mem[1]) && ctype_digit(trim($mem[1])) ? $mem[1] : 0;
                        $this->freepbx->astman->database_put(sprintf('QPENALTY/%d/agents',
                                                                     $args['id']), trim($mem[0]),
                                                             trim($penalty));
                    }
                }

                // Restore dynmemberonly
                $this->freepbx->astman->database_put(sprintf('QPENALTY/%d', $args['id']),
                                                     'dynmemberonly',
                                                     $dynmemberonly ? $dynmemberonly : 0);
            }

            needreload();
            return $response->withJson(true);
        })->add($this->checkAllReadScopeMiddleware());

        /**
         * @verb    GET
         * @returns - a list of queue settings
         * @uri     /queues/:id
         */
        $app->get('/{id}', function($request, $response, $args) {
            $queue = queues_get($args['id']);

            if ($queue) {
                $queue['extension'] = $args['id'];

                $dynmembers = [];
                foreach (explode("\n", $queue['dynmembers']) as $member) {
                    $dynmembers[] = substr($member, 0, strpos($member, ","));
                }
                $queue['dynmembers'] = $dynmembers;

                $members = [];
                foreach ($queue['member'] as $member) {
                    if (preg_match("/^.*?\/([\d]+).*,([\d]+)$/", $member, $matches)) {
                        $members[] = $matches[1];
                    }
                }
                $queue['member'] = $members;
            }

            $queue = $queue ? $queue : false;
            return $response->withJson($queue);
        })->add($this->checkAllReadScopeMiddleware());
    }

}
