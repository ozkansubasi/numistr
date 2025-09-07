<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Factory;

$controller = BaseController::getInstance('NumistrAdmin');
$controller->execute(Factory::getApplication()->input->get('task'));
$controller->redirect();
