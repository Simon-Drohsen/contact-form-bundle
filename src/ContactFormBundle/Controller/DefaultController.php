<?php

namespace Instride\Bundle\ContactFormBundle\Controller;

use Pimcore\Controller\FrontendController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends FrontendController
{
    /**
     * @Route("/contact_form")
     */
    public function indexAction(Request $request): Response
    {
        $html = $this->renderView('@ContactFormBundle/form/index.html.twig');

        return new Response($html);
    }
}
