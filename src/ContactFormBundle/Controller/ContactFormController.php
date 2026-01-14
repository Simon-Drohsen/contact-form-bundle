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
use Symfony\Component\Routing\Attribute\Route;

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

    #[Route('/contact_form', name: 'contact_form_site', methods: ['GET', 'POST'])]
    #[Route('/{prefix}/contact_form', name: 'contact_form_subsite', requirements: ['prefix' => '[a-z0-9\-]+' ], methods: ['GET', 'POST'])]
    public function contactFormAction(Request $request, ?string $prefix = null): Response
    {
        $routeName = $prefix ? 'contact_form_subsite' : 'contact_form_site';
        $routeParams = $prefix ? ['prefix' => $prefix] : [];

        $form = $this->formFactory->create(ContactFormType::class, null, [
            'action' => $this->generateUrl($routeName, $routeParams),
            'method' => 'POST',
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $customRedirect = (string) ($this->document?->getProperty('contact_form_redirect_site') ?: '/');
            $redirect = $this->redirect($customRedirect, Response::HTTP_SEE_OTHER);
            $obj = $this->createFormObject($form->getData());

            if (!\is_null($obj)) {
                list($mailTitle, $adminMailText, $userMailText) = $this->getMailTexts();
                $this->createAndSendMail($obj, $mailTitle, $adminMailText, $userMailText, $request->getHost());
            }

            return $redirect;
        }

        return $this->render('@ContactForm/form/view.html.twig', ['form' => $form->createView()]);
    }

    private function createFormObject(array $formValues): ?DataObject\FormValue
    {
        $parent = $this->resolveParent();
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

    private function resolveParent(): DataObject\AbstractObject
    {
        try {
            $setting = WebsiteSetting::getByName('contact_form_parent_folder');
            $parent = $setting?->getData();
            if ($parent instanceof DataObject\AbstractObject) {
                return $parent;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('Error retrieving parent folder setting: ' . $e->getMessage());
        }

        return DataObject::getById(1);
    }

    /**
     * @return string[]
     */
    public function getMailTexts(): array
    {
        $mailTitle = '';
        $adminMailText = '';
        $userMailText = '';

        if ($this->document instanceof Document && method_exists($this->document, 'getEditable')) {
            $mailTitle = (string) ($this->document->getEditable('mailTitle')?->getText() ?? '');
            $adminMailText = (string) ($this->document->getEditable('adminMailText')?->getText() ?? '');
            $userMailText = (string) ($this->document->getEditable('userMailText')?->getText() ?? '');
        }

        return [$mailTitle, $adminMailText, $userMailText];
    }

    private function createAndSendMail(
        DataObject\FormValue $obj,
        string $mailTitle,
        string $adminMailText,
        string $userMailText,
        string $mainDomain
    ): void {
        $objLink = "https://{$mainDomain}/admin/login/deeplink?object_{$obj->getId()}_object";
        $adminMailName = Config::getSystemConfiguration('email')['sender']['name'];
        $params = [
            'firstname' => $obj->getFirstname(),
            'lastname' => $obj->getLastname(),
            'message' => $obj->getMessage(),
            'admin' => $adminMailName,
            'mailTitle' => $mailTitle,
        ];

        $userMail = $this->renderView('@ContactForm/mail/user.html.twig', [
            'params' => $params,
            'mailText' => $userMailText,
        ]);

        $adminMail = $this->renderView('@ContactForm/mail/admin.html.twig', [
            'params' => $params,
            'mailText' => $adminMailText,
            'objLink' => $objLink,
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

        if ($userMail) $this->sendMail($obj->getEmail(), $userMail, $mailTitle);
        if ($adminMail) $this->sendMail($adminMailAddress, $adminMail, $mailTitle);
    }

    private function sendMail(string $to, string $html, string $subject, string $context = 'mail'): void
    {
        $mail = new Mail();
        $mail->to($to);
        $mail->subject($subject);
        $mail->html($html);

        try {
            $mail->send();
        } catch (\Throwable $e) {
            $this->logger->error(sprintf('Error sending %s to %s: %s', $context, $to, $e->getMessage()));
        }
    }
}
