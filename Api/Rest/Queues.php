<?php
namespace FreePBX\modules\Queues\Api\Rest;
use FreePBX\modules\Api\Rest\Base;
class Queues extends Base {
	protected $module = 'queues';
	public function setupRoutes($app) {
		/**
		* @verb GET
		* @returns - the queue list
		* @uri /queues
		*/
		$app->get('/', function ($request, $response, $args) {
			\FreePBX::Modules()->loadFunctionsInc('queues');
			$queues = queues_list();

			foreach ($queues as $queue) {
				$entry = new \stdClass();
				$entry->extension = $queue[0];
				$entry->name = $queue[1];
				$list[$queue[0]] = $entry;
			}

			$list = $list ? $list : false;
			return $response->withJson($list);
		})->add($this->checkAllReadScopeMiddleware());

		/**
		 * @verb GET
		 * @returns - a list of queues with their members (static and dynamic)
		 * @uri /queues/members
		 */
		$app->get('/members', function ($request, $response, $args) {
			\FreePBX::Modules()->loadFunctionsInc('queues');
			$list = array();

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

			$list = $list ? $list : false;
			return $response->withJson($list);
		})->add($this->checkAllReadScopeMiddleware());

		/**
		 * @verb GET
		 * @returns - a list of queue settings
		 * @uri /queue/:id
		 */
		$app->get('/{id}', function ($request, $response, $args) {
			$queue = queues_get($args['id']);

			if ($queue) {
				$queue['extension'] = $args['id'];

				$dynmembers = array();
				foreach (explode("\n", $queue['dynmembers']) as $member) {
					$dynmembers[] = substr($member, 0, strpos($member, ","));
				}
				$queue['dynmembers'] = $dynmembers;

				$members = array();
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
