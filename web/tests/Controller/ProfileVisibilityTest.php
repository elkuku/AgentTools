<?php

declare(strict_types=1);

namespace App\Tests\Controller;

use App\Entity\User;
use App\Enum\ProfilePart;
use App\Repository\UserRepository;
use App\Security\FakeGoogleIdTokenVerifier;
use App\Tests\Fake\GoogleSignIn;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Field\ChoiceFormField;
use Symfony\Component\HttpFoundation\Request;

class ProfileVisibilityTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = static::createClient();
    }

    protected function tearDown(): void
    {
        $this->entityManager()
            ->createQuery("UPDATE App\\Entity\\User u SET u.googleId = NULL, u.agentName = NULL, u.visibleParts = '[]', u.visibilityChosenAt = NULL")
            ->execute();

        parent::tearDown();
    }

    public function testEverythingIsPrivateUntilTheUserChooses(): void
    {
        $this->client->loginUser($this->user());
        $this->client->request(Request::METHOD_GET, '/profile');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('#visibility-not-chosen', 'everything is private');
        self::assertCheckboxNotChecked('profile_visibility[visibleParts][]', 'agent');
    }

    public function testSavesTheVisibleParts(): void
    {
        $this->client->loginUser($this->user());
        $this->submit([ProfilePart::AGENT, ProfilePart::CUSTOM_MEDALS]);

        self::assertResponseRedirects('/profile');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'Your profile visibility has been saved.');
        self::assertSelectorNotExists('#visibility-not-chosen');

        $this->entityManager()->clear();
        $user = $this->user();
        self::assertSame([ProfilePart::AGENT, ProfilePart::CUSTOM_MEDALS], $user->getVisibleParts());
        self::assertNotNull($user->getVisibilityChosenAt());
    }

    public function testNothingIsShownWithoutTheAgentPart(): void
    {
        $this->client->loginUser($this->user());
        $this->submit([ProfilePart::LATEST_STATS, ProfilePart::CUSTOM_MEDALS]);

        $this->entityManager()->clear();
        $user = $this->user();
        self::assertSame([], $user->getVisibleParts());
        // Chosen nevertheless: all private
        self::assertNotNull($user->getVisibilityChosenAt());
    }

    public function testTheFirstGoogleSignInLeadsToTheProfileUntilTheUserChooses(): void
    {
        $idToken = FakeGoogleIdTokenVerifier::token('gid-visibility', 'user');

        GoogleSignIn::post($this->client, $idToken);
        self::assertResponseRedirects('/profile');

        $this->submit([]);
        $this->client->request(Request::METHOD_GET, '/logout');

        GoogleSignIn::post($this->client, $idToken);
        self::assertResponseRedirects('/');
    }

    /**
     * @param list<ProfilePart> $parts
     */
    private function submit(array $parts): void
    {
        $crawler = $this->client->request(Request::METHOD_GET, '/profile');
        $form = $crawler->selectButton('Save')->form();

        /** @var list<ChoiceFormField> $checkboxes */
        $checkboxes = $form['profile_visibility[visibleParts]'];
        foreach ($checkboxes as $checkbox) {
            $value = $checkbox->availableOptionValues()[0];
            \in_array(ProfilePart::from($value), $parts, true) ? $checkbox->tick() : $checkbox->untick();
        }

        $this->client->submit($form);
    }

    private function user(): User
    {
        /** @var UserRepository $userRepository */
        $userRepository = static::getContainer()->get(UserRepository::class);
        $user = $userRepository->findOneBy(['identifier' => 'user']);
        self::assertNotNull($user);

        return $user;
    }

    private function entityManager(): EntityManagerInterface
    {
        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        return $entityManager;
    }
}
