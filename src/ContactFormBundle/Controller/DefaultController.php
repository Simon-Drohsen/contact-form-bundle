<?php

namespace Instride\Bundle\ContactFormBundle\Controller;

use Instride\Bundle\ContactFormBundle\Form\Type\ContactFormType;
use Pimcore\Controller\FrontendController;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DefaultController extends FrontendController
{
    private FormFactoryInterface $formFactory;

    public function __construct(FormFactoryInterface $formFactory) {
        $this->formFactory = $formFactory;
    }

    #[Route('/contact_form', name: 'contact_form_homepage', methods: ['GET', 'POST'])]
    public function indexAction(Request $request): Response
    {
        $form = $this->formFactory->create(ContactFormType::class, null, [
            'action' => $this->generateUrl('contact_form_homepage'),
            'method' => 'POST',
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $redirect = $this->document->getProperty('contact_form_redirect_site');

                return $this->redirect($redirect ?: '/');
            }
        }

        $html = $this->renderView('@ContactFormBundle/form/view.html.twig', [
            'form' => $form->createView(),
        ]);

        return new Response($html);
    }
}
