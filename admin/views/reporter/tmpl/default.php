<?php
defined('_JEXEC') or die('Restricted access');

//load tooltip behavior
JHtml::_('behavior.tooltip');
?>
<form action="<?php echo JRoute::_('index.php?option=com_importxml'); ?>" method="post" name="adminForm" id="adminForm">
	<table class="adminlist">
    	<thead><tr>
			<th><?php echo JText::_('COM_IMPORTXML_UPLOAD_ID'); ?></th>
			<th><?php echo JText::_('COM_IMPORTXML_USER_ID'); ?></th>
			<th><?php echo JText::_('COM_IMPORTXML_FILENAME'); ?></th>
			<th><?php echo JText::_('COM_IMPORTXML_TIMESTAMP'); ?></th>
			<th><?php echo JText::_('COM_IMPORTXML_NUM_ADDED'); ?></th>
			<th><?php echo JText::_('COM_IMPORTXML_NUM_UPDATED'); ?></th>
			<th><?php echo JText::_('COM_IMPORTXML_NUM_DELETED'); ?></th>
			<th><?php echo JText::_('COM_IMPORTXML_NUM_ERRORED'); ?></th>
			<th><?php echo JText::_('COM_IMPORTXML_NUM_TOTAL'); ?></th>
		</tr></thead>
        <tfoot><tr>
        	<td colspan="9"><?php echo $this->pagination->getListFooter(); ?></td>
        </tr></tfoot>
        <tbody><?php foreach($this->items as $i => $item): ?><tr class="row<?php echo $i % 2; ?>">
	        <?php $item->link = 'index.php?option=com_importxml&view=details&id='.$item->upload_id; ?>
        	<td><a href="<?php echo $item->link; ?>"><?php echo $item->upload_id; ?></a></td>
            <td><a href="<?php echo $item->link; ?>"><?php echo $item->user_id; ?></a></td>
            <td><a href="<?php echo $item->link; ?>"><?php echo $item->filename; ?></a></td>
            <td><a href="<?php echo $item->link; ?>"><?php echo $item->timestamp; ?></a></td>
            <td><a href="<?php echo $item->link; ?>"><?php echo $item->added_events; ?></a></td>
            <td><a href="<?php echo $item->link; ?>"><?php echo $item->updated_events; ?></a></td>
            <td><a href="<?php echo $item->link; ?>"><?php echo $item->deleted_events; ?></a></td>
            <td><a href="<?php echo $item->link; ?>"><?php echo $item->errored_events; ?></a></td>
            <td><a href="<?php echo $item->link; ?>"><?php echo $item->total_events; ?></a></td>
        </tr><?php endforeach; ?></tbody>
    </table>
    <input type="hidden" name="task" value="" />
</form>
