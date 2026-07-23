<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Auth\AuthSession;
use App\Auth\Csrf;
use App\Auth\CurrentUser;
use App\Auth\SessionManager;
use App\Models\Person;
use App\Models\PersonPage;
use App\Models\UserLinkOption;
use App\Support\Flash;
use App\Support\UrlGenerator;
use App\Support\View;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\InMemoryUserRepository;
use Tests\Support\UserFactory;

final class UsernameDisplayTest extends TestCase
{
    protected function setUp(): void { $_SESSION = []; }

    public function testNavigationAndUserListDoNotPrefixUsername(): void
    {
        $user = UserFactory::make(username: 'luca.tonin', email: 'luca.tonin@unipd.it');
        $session = new AuthSession(new SessionManager(), 1800, 28800);
        $session->login(1);
        $view = $this->view(new CurrentUser($session, new InMemoryUserRepository([$user])));
        $html = $view->render('admin/users/index', ['title'=>'Users','users'=>[$user],'csrfToken'=>'token']);
        self::assertStringContainsString('luca.tonin', $html);
        self::assertStringNotContainsString('@luca.tonin', $html);
        self::assertStringContainsString('luca.tonin@unipd.it', $html);
    }

    public function testPeopleListAndSelectorDoNotPrefixUsernameAndHideNotes(): void
    {
        $now = new DateTimeImmutable('2026-01-01');
        $person = new Person(1, 1, 'Luca', 'Tonin', 'luca.tonin@unipd.it', 'University', 'full_professor', true, null, null, true, 'secret-note-marker', $now, $now, 'luca.tonin');
        $view = $this->view(null);
        $html = $view->render('admin/people/index', [
            'title'=>'People','page'=>new PersonPage([$person],1,1,25),
            'filters'=>['search'=>'','active'=>'active','internal'=>'all','position_type'=>'','linked'=>'all'],
            'positionLabels'=>Person::POSITION_LABELS,'csrfToken'=>'token',
        ]);
        self::assertStringContainsString('luca.tonin', $html);
        self::assertStringNotContainsString('@luca.tonin', $html);
        self::assertStringContainsString('luca.tonin@unipd.it', $html);
        self::assertStringNotContainsString('secret-note-marker', $html);
        $form = $view->render('admin/people/form', [
            'title'=>'Edit person','mode'=>'edit','person'=>$person,'errors'=>[],
            'values'=>['user_id'=>'1','first_name'=>'<Luca>','last_name'=>'Tonin','institutional_email'=>'luca.tonin@unipd.it','affiliation'=>'<University>','position_type'=>'full_professor','is_internal'=>'1','active_from'=>'','active_to'=>'','is_active'=>'1','notes'=>'<private>'],
            'positionLabels'=>Person::POSITION_LABELS,
            'userOptions'=>[new UserLinkOption(1,'luca.tonin','Luca','Tonin','luca.tonin@unipd.it','admin',true)],
            'csrfToken'=>'token',
        ]);
        self::assertStringNotContainsString('@luca.tonin', $form);
        self::assertStringContainsString('&lt;Luca&gt;', $form);
        self::assertStringContainsString('&lt;University&gt;', $form);
        self::assertStringContainsString('&lt;private&gt;', $form);
    }

    private function view(?CurrentUser $current): View
    {
        return new View(dirname(__DIR__, 2).'/views', new UrlGenerator('https://example.test'), new Flash(), $current, new Csrf());
    }
}
