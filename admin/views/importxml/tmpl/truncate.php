<?php
defined('_JEXEC') or die('Restricted access');

//load tooltip behavior
JHtml::_('behavior.tooltip');
?>
<form action="<?php echo JRoute::_('index.php?option=com_importxml'); ?>" enctype="multipart/form-data" method="post" name="adminForm" id="adminForm">
	<fieldset class="adminForm">
    	<legend><?php echo JText::_('COM_IMPORTXML_TRUNCATE_EVENTS_LEGEND');?></legend>
        <p><?php echo JText::_('COM_IMPORTXML_TRUNCATE_EVENTS_CONFIRM_MSG');?></p>
		<input type="hidden" name="task" value="importxml.truncateconfirmed" />
        <input type="submit" value="<?php echo JText::_('CONFIRM');?>" />
        <?php echo JHtml::_('form.token'); ?>
    </fieldset>
</form>