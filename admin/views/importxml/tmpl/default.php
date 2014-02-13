<?php
defined('_JEXEC') or die('Restricted access');

//load tooltip behavior
JHtml::_('behavior.tooltip');
?>
<form action="<?php echo JRoute::_('index.php?option=com_importxml'); ?>" enctype="multipart/form-data" method="post" name="adminForm" id="adminForm">
	<fieldset class="adminForm">
    	<legend><?php echo JText::_('COM_IMPORTXML_UPLOAD_LEGEND');?></legend>
        <label for="jevent_import" class="hasTip" title="<?php echo JText::_('COM_IMPORTXML_JEVENT_IMPORT_LABEL').'::'.JText::_('COM_IMPORTXML_JEVENT_IMPORT_DESCRIPTION');?>"><?php echo JText::_('COM_IMPORTXML_JEVENT_IMPORT_LABEL'); ?></label>
        <input type="file" name="jevent_import" />
		<input type="hidden" name="task" value="upload.upload" />
        <input type="submit" value="<?php echo JText::_('IMPORT');?>" />
        <?php echo JHtml::_('form.token'); ?>
    </fieldset>
</form>