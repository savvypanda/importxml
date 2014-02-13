<?php
defined('_JEXEC') or die('Restricted access');

//load tooltip behavior
JHtml::_('behavior.tooltip');
?>
<form action="<?php echo JRoute::_('index.php?option=com_importxml'); ?>" method="post" name="adminForm" id="adminForm">
	<h3><?php echo JText::_('Import');?> <?php echo $this->filename;?>: <?php echo $this->timestamp; ?></h3>
	<table class="adminlist">
    	<thead><tr>
			<th><?php echo JText::_('COM_IMPORTXML_DETAILS_ID'); ?></th>
			<th><?php echo JText::_('COM_IMPORTXML_EVENT_IMPORT_ID'); ?></th>
			<th><?php echo JText::_('COM_IMPORTXML_EVENT_JEVENT_ID'); ?></th>
			<th><?php echo JText::_('COM_IMPORTXML_EVENT_STATUS'); ?></th>
			<th><?php echo JText::_('COM_IMPORTXML_EVENT_DETAILS'); ?></th>
		</tr></thead>
        <tfoot><tr>
        	<td colspan="5"><?php echo $this->pagination->getListFooter(); ?></td>
        </tr></tfoot>
        <tbody><?php foreach($this->items as $i => $item): ?><tr class="row<?php echo $i % 2; ?>">
        	<td><?php echo $item->details_id; ?></td>
            <td><?php echo $item->import_id; ?></td>
            <td><?php echo $item->jevent_id; ?></td>
            <td><?php echo $item->status; ?></td>
            <td><?php echo $item->details; ?></td>
        </tr><?php endforeach; ?></tbody>
    </table>
    <input type="hidden" name="task" value="" />
</form>