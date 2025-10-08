<?php
namespace Joomla\Component\Numistr\Api\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;

class VariantsController extends BaseController
{
    protected $contentType  = 'variants';
    protected $default_view = 'variants';

    // GET /api/v1/variants
    public function displayList()
    {
        // Kanıt: controller'a girildi
        @file_put_contents(JPATH_ROOT . '/numistr_api_probe.txt', "CTRL:list\n", FILE_APPEND);

        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        echo json_encode([
            'data' => [],
            'meta' => ['total' => 0, 'page' => 1, 'per_page' => 20]
        ], JSON_UNESCAPED_UNICODE);
        $app->close(); // Çık
    }

    // GET /api/v1/variants/{id}
    public function displayItem($id = null)
    {
        // Kanıt: controller'a girildi
        @file_put_contents(JPATH_ROOT . '/numistr_api_probe.txt', "CTRL:item\n", FILE_APPEND);

        $id  = $id ?? $this->input->get('id', '', 'cmd');
        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        echo json_encode(['uid' => $id], JSON_UNESCAPED_UNICODE);
        $app->close();
    }
}
