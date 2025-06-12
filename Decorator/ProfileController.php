<?php

namespace KimaiPlugin\RPDBundle\Decorator;

use App\Entity\User;
use App\Repository\UserRepository;
use KimaiPlugin\RPDBundle\Form\UserContractType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class ProfileController extends \App\Controller\ProfileController
{

    #[Route(path: '/{username}/contract', name: 'user_profile_contract', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[IsGranted('contract', 'profile')]
    public function contractAction(User $profile, Request $request, UserRepository $userRepository): Response
    {
        $form = $this->createForm(UserContractType::class, $profile, [
            'action' => $this->generateUrl('user_profile_contract', ['username' => $profile->getUserIdentifier()]),
            'method' => 'POST',
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userRepository->saveUser($profile);
            $this->flashSuccess('action.update.success');

            return $this->redirectToRoute('user_profile_contract', ['username' => $profile->getUserIdentifier()]);
        }

        return $this->render('@RPD/user/contract.html.twig', [
            'tab' => 'contract',
            'page_setup' => $this->getPageSetup($profile, 'contract'),
            'user' => $profile,
            'form' => $form->createView(),
        ]);
    }
}