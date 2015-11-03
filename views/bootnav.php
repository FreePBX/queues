<div id="toolbar-qrnav">
  <a href="config.php?display=queues" class="btn btn-default"><i class="fa fa-list"></i>&nbsp; <?php echo _("List Queues") ?></a>
  <a href="config.php?display=queues&amp;view=form" class="btn btn-primary"><i class="fa fa-plus"></i> <?php echo _("Add Queue") ?></a>

</div>
<table data-url="ajax.php?module=queues&amp;command=getJSON&amp;jdata=grid" data-toolbar="#toolbar-qrnav" data-cache="false" data-toggle="table" data-search="true" class="table" id="table-all-side">
    <thead>
        <tr>
            <th data-sortable="true" data-field="extension" data-formatter="qrnavformatter"><?php echo _('Queue')?></th>
        </tr>
    </thead>
</table>
<script type="text/javascript">
  function qrnavformatter(r,v){
    return '<a href="?display=queues&view=form&extdisplay='+v.extension+'">'+v.description+' ('+v.extension+')</a>';
  }
</script>
