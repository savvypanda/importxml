<?php
defined('_JEXEC') or die('Restricted access');
?>

<div>
<h3><?php echo JText::_('COM_IMPORTXML_INSTALLED_HEADER'); ?></h3>
<p><?php echo JText::_('COM_IMPORTXML_INSTALLED_MSG'); ?></p>
<p><input type="button" onclick="window.location.href='index.php?option=com_importxml&task=importxml.truncate'" value="<?php echo JText::_('COM_IMPORTXML_TRUNCATE_EVENTS_BTN');?>" /></p>
</div>
