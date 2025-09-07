<?php
defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\ApiController;
use Joomla\CMS\Factory;

class NumistrControllerImages extends ApiController
{
    protected $contentType = 'application/json';
    protected $default_view = 'images';

    public function getItem()
    {
        $app   = Factory::getApplication();
        $id    = $app->input->getInt('id', 0);
        $model = $this->getModel('Images');

        $row = $model->fetchOne($id);
        if (!$row) {
            http_response_code(404);
            return $this->displayJson(['type'=>'about:blank','title'=>'Not Found','status'=>404,'detail'=>'image not found']);
        }
        $this->displayJson($model->serializeOne($row));
    }
}
