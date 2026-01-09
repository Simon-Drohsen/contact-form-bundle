<?php

namespace Instride\Bundle\ContactFormBundle\Controller;

use Exception;
use Instride\Bundle\ContactFormBundle\Form\Type\ContactFormType;
use Pimcore\Bundle\ApplicationLoggerBundle\ApplicationLogger;
use Pimcore\Config;
use Pimcore\Controller\FrontendController;
use Pimcore\Mail;
use Pimcore\Model\DataObject;
use Pimcore\Model\Document;
use Pimcore\Model\WebsiteSetting;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ContactFormController extends FrontendController
{
    private FormFactoryInterface $formFactory;
    private ApplicationLogger $logger;

    public function __construct(
        FormFactoryInterface $formFactory,
        ApplicationLogger $logger
    ) {
        $this->formFactory = $formFactory;
        $this->logger = $logger;
    }

    #[Route('/{_locale}/contact_form', name: 'contact_form', methods: ['GET', 'POST'])]
    public function contactFormAction(Request $request): Response
    {
        $form = $this->formFactory->create(ContactFormType::class, null, [
            'action' => $this->generateUrl('contact_form'),
            'method' => 'POST',
        ]);

        $document = Document::getById($this->document->getId());
        $mailTitle = '';
        $adminMailText = '';
        $userMailText = '';

        if ($document instanceof Document) {
            if (method_exists($document, 'getEditable')) {
                $mailTitleEditable = $document->getEditable('mailTitle');
                $adminEditable = $document->getEditable('adminMailText');
                $userEditable = $document->getEditable('userMailText');

                if (!\is_null($mailTitleEditable)) {
                    $mailTitle = (string)$mailTitleEditable->getText();
                }
                if (!\is_null($adminEditable)) {
                    $adminMailText = (string)$adminEditable->getText();
                }
                if (!\is_null($userEditable)) {
                    $userMailText = (string)$userEditable->getText();
                }
            }
        }

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $customRedirect = $this->document->getProperty('contact_form_redirect_site');
                $redirect = $this->redirect($customRedirect ?: '/');
                $obj = $this->createFormObject($form->getData());

                if (!\is_null($obj)) {
                    $this->createAndSendMail($obj, $mailTitle, $adminMailText, $userMailText, $request->getHost());
                }

                return $redirect;
            }
        }

        $html = $this->renderView('@ContactFormBundle/form/view.html.twig', [
            'form' => $form->createView(),
        ]);

        return new Response($html);
    }

    private function createFormObject(array $formValues): ?DataObject\FormValue
    {
        try {
            $parent = WebsiteSetting::getByName('contact_form_parent_folder');
        } catch (Exception $exception) {
            $this->logger->warning('Error retrieving contact form parent folder setting: ' . $exception->getMessage());
            $parent = null;
        }

        if (\is_null($parent)) {
            $parent = DataObject::getById(1);
        } else {
            $parent = $parent->getData();
        }

        $firstname = \trim((string) ($formValues['firstname'] ?? ''));
        $lastname = \trim((string) ($formValues['lastname'] ?? ''));
        $email = \trim((string) ($formValues['email'] ?? ''));
        $message = \trim((string) ($formValues['message'] ?? ''));

        $obj = new DataObject\FormValue();
        $obj->setFirstname($firstname);
        $obj->setLastname($lastname);
        $obj->setEmail($email);
        $obj->setMessage($message);
        $obj->setKey($email . '_' . \time());
        $obj->setParent($parent);

        try {
            $obj->setPublished(true);
            $obj->save();

            return $obj;
        } catch (Exception $e) {
            $this->logger->error('Error saving contact form data: ' . $e->getMessage());
        }

        return null;
    }

    private function createAndSendMail(
        DataObject\FormValue $obj,
        string $mailTitle,
        string $adminMailText,
        string $userMailText,
        string $mainDomain
    ): void {
        $objLink = sprintf("https://%1s/admin/login/deeplink?object_%2d_object", $mainDomain, $obj->getId());
        $adminMailName = Config::getSystemConfiguration('email')['sender']['name'];
        $params = [
            'firstname' => $obj->getFirstname(),
            'lastname' => $obj->getLastname(),
            'message' => $obj->getMessage(),
            'admin' => $adminMailName,
            'mailTitle' => $mailTitle,
        ];

        $userMail = $this->renderView('@ContactFormBundle/mail/user.html.twig', [
            'params' => $params,
            'mailText' => $userMailText
        ]);

        $adminMail = $this->renderView('@ContactFormBundle/mail/admin.html.twig', [
            'params' => $params,
            'mailText' => $adminMailText,
            'objLink' => $objLink
        ]);

        try {
            $adminMailAddress = WebsiteSetting::getByName('contact_form_admin_mail');
        } catch (Exception $e) {
            $this->logger->warning('Error retrieving admin email setting: ' . $e->getMessage());
            $adminMailAddress = null;
        }

        if (\is_null($adminMailAddress)) {
            $adminMailAddress = Config::getSystemConfiguration('email')['sender']['email'];
        } else {
            $adminMailAddress = $adminMailAddress->getData();
        }

        if ($userMail) {
            $this->sendMail($obj->getEmail(), $userMail, $mailTitle);
        }
        if ($adminMail) {
            $this->sendMail($adminMailAddress, $adminMail, $mailTitle);
        }
    }

    private function sendMail(string $email, string $mailTemplate, string $mailSubject): void
    {
        $mail = new Mail();
        $mail->to($email);
        $mail->subject($mailSubject);
        $mail->html($mailTemplate);

        try {
            $mail->send();
        } catch (Exception $e) {
            $this->logger->error('Error sending user email: ' . $e->getMessage());
        }
    }
}
