<?php

namespace KimaiPlugin\RPDBundle\Vacation;

use App\Entity\User;
use KimaiPlugin\RPDBundle\Entity\Vacation;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class VacationMailer
{

    public function __construct(private readonly Environment $twig, private readonly MailerInterface $mailer)
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
}