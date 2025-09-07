<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class NumistrAdminViewDashboard extends BaseHtmlView
{
    public function display($tpl = null)
    {
        ToolbarHelper::title('Numistr Bileşeni', 'stack');
        ToolbarHelper::preferences('com_numistr');
        parent::display($tpl);
    }
}
