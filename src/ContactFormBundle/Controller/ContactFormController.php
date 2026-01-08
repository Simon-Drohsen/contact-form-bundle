<?php

namespace Instride\Bundle\ContactFormBundle\Controller;

use Exception;
use Instride\Bundle\ContactFormBundle\Form\Type\ContactFormType;
use Pimcore\Config;
use Pimcore\Controller\FrontendController;
use Pimcore\Mail;
use Pimcore\Model\DataObject\FormValue;
use Pimcore\Model\WebsiteSetting;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ContactFormController extends FrontendController
{
    private FormFactoryInterface $formFactory;

    public function __construct(FormFactoryInterface $formFactory)
    {
        $this->formFactory = $formFactory;
    }

    /**
     * @throws Exception
     */
    #[Route('/{_locale}/contact_form', name: 'contact_form', methods: ['GET', 'POST'])]
    public function contactFormAction(Request $request): Response
    {
        $form = $this->formFactory->create(ContactFormType::class, null, [
            'action' => $this->generateUrl('contact_form'),
            'method' => 'POST',
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                $redirect = $this->document->getProperty('contact_form_redirect_site');

                $obj = $this->createFormObject($form->getData());

                if (\is_null($obj)) {
                    // Handle error saving form data
                    // For example, you could add a flash message or log the error
                }

                return $this->redirect($redirect ?: '/');
            }
        }

        $html = $this->renderView('@ContactFormBundle/form/view.html.twig', [
            'form' => $form->createView(),
        ]);

        return new Response($html);
    }

    /**
     * @throws Exception
     */
    private function createFormObject(array $formValues): ?FormValue
    {
        $parent = WebsiteSetting::getByName('contact_form_parent_folder');

        if (!$parent) $parent = 1;
        else $parent = $parent->getData();

        $firstname = \trim($formValues['firstname']) ?? null;
        $lastname = \trim($formValues['lastname']) ?? null;
        $email = \trim($formValues['email']) ?? null;
        $message = \trim($formValues['message']) ?? null;

        $obj = new FormValue();
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
            dd('Error saving contact form data: ' . $e->getMessage());
            // $this->logger->error('Error saving contact form data: ' . $e->getMessage());
        }

        return null;
    }

    /**
     * @throws Exception
     */
    private function createAndSendMail(FormValue $obj): void
    {
        $adminMailName = Config::getSystemConfiguration('email')['sender']['name'];
        $params = [
            'firstname' => $obj->getFirstname(),
            'lastname' => $obj->getLastname(),
            'message' => $obj->getMessage(),
            'admin' => $adminMailName,
        ];
        $userMail = $this->renderView('@ContactFormBundle/mail/user.html.twig', ['params' => $params]);
        $adminMail = $this->renderView('@ContactFormBundle/mail/admin.html.twig', ['params' => $params]);
        $adminMailAddress = WebsiteSetting::getByName('contact_form_admin_mail');

        if (!$adminMailAddress) $adminMailAddress = Config::getSystemConfiguration('email')['sender']['email'];
        else $adminMailAddress = $adminMailAddress->getData();

        if ($userMail) $this->sendMail($obj->getEmail(), $userMail);
        if ($adminMail) $this->sendMail($adminMailAddress, $adminMail);
    }

    private function sendMail(string $email, string $mailTemplate): void
    {
        $mail = new Mail();
        $mail->to($email);
        $mail->html($mailTemplate);

        try {
            $mail->send();
        } catch (Exception $e) {
            dd('Error sending user email: ' . $e->getMessage());
        }
    }
}
