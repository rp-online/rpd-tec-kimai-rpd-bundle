<?php

namespace KimaiPlugin\RPDBundle\Vacation;

use App\Configuration\SystemConfiguration;
use App\Entity\User;
use KimaiPlugin\RPDBundle\Entity\Vacation;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class VacationMailer
{

    public function __construct(
        private readonly Environment $twig,
        private readonly MailerInterface $mailer,
        private readonly SystemConfiguration $systemConfiguration
    )
    {
    }

    public function sendNewVacationRequest(Vacation $vacation): void
    {
        $content = $this->twig->render('@RPD/mail/new_vacation_request.txt.twig', ['vacation' => $vacation]);
        $email = (new Email())
            ->from('info@park.works')
            ->to(...$this->getTeamLeads($vacation))
            ->subject('Neuer Urlaubsantrag von ' . $vacation->getUser()->getDisplayName())
            ->text($content);

        $this->mailer->send($email);
    }

    protected function getTeamLeads(Vacation $vacation): array
    {
        $teamLeads = [];
        foreach($vacation->getUser()->getTeams() as $team) {
            foreach($team->getTeamleads() as $user) {
                $teamLeads[] = $user->getEmail();
            }
        }

        return $teamLeads;
    }

    public function sendVacationRevoked(Vacation $vacation): void
    {
        $content = $this->twig->render('@RPD/mail/vacation_revoked.txt.twig', ['vacation' => $vacation]);
        $email = (new Email())
            ->from('info@park.works')
            ->to(...$this->getTeamLeads($vacation))
            ->subject($vacation->getUser()->getDisplayName() . ' hat seinen Urlaub widerrufen')
            ->text($content);

        $this->mailer->send($email);
    }

    public function sendVacationApproved(Vacation $vacation): void
    {
        $content = $this->twig->render('@RPD/mail/vacation_approved.txt.twig', ['vacation' => $vacation]);
        $leads = $this->getTeamLeads($vacation);
        $sender = $vacation->getApprovedBy()->getEmail();
        $leads = array_filter($leads, function($lead) use ($sender) {
            return $lead !== $sender;
        });
        $email = (new Email())
            ->from('info@park.works')
            ->to($vacation->getUser()->getEmail())
            ->subject('Urlaub genehmigt!')
            ->text($content);
        if(!empty($leads)) {
            $email->cc(...$leads);
        }
        $this->sendInfoMailToHR($vacation);
        $this->mailer->send($email);
    }

    public function sendVacationDeclined(Vacation $vacation): void
    {
        $content = $this->twig->render('@RPD/mail/vacation_declined.txt.twig', ['vacation' => $vacation]);
        $leads = $this->getTeamLeads($vacation);
        if($vacation->getApprovedBy()) {
            $sender = $vacation->getApprovedBy()->getEmail();
            $leads = array_filter($leads, function ($lead) use ($sender) {
                return $lead !== $sender;
            });
        }
        $email = (new Email())
            ->from('info@park.works')
            ->to($vacation->getUser()->getEmail())
            ->subject('Urlaub abgelehnt!')
            ->text($content);
        if(!empty($leads)) {
            $email->cc(...$leads);
        }

        $this->mailer->send($email);
    }

    public function sendVacationAdded(Vacation $vacation): void
    {
        $content = $this->twig->render('@RPD/mail/vacation_added.txt.twig', ['vacation' => $vacation]);
        $leads = $this->getTeamLeads($vacation);
        if($vacation->getApprovedBy()) {
            $sender = $vacation->getApprovedBy()->getEmail();
            $leads = array_filter($leads, function ($lead) use ($sender) {
                return $lead !== $sender;
            });
        }
        $email = (new Email())
            ->from('info@park.works')
            ->to($vacation->getUser()->getEmail())
            ->subject('Urlaub für dich hinzugefügt!')
            ->text($content);
        if(!empty($leads)) {
            $email->cc(...$leads);
        }

        $this->mailer->send($email);
    }

    protected function sendInfoMailToHR(Vacation $vacation): void
    {
        $hrEmail = $this->systemConfiguration->find('vacation.hr_email_address');
        if(empty($hrEmail) || empty($vacation->getUser()->getAccountNumber())) {
            return;
        }
        $content = $this->twig->render('@RPD/mail/vacation_information.txt.twig', [
            'vacation' => $vacation,
            'company' => $this->systemConfiguration->find('theme.branding.company')]
        );
        $email = (new Email())
            ->from('info@park.works')
            ->to($hrEmail)
            ->subject('Urlaubsantrag von ' . $vacation->getUser()->getDisplayName())
            ->text($content);

        $this->mailer->send($email);
    }
}