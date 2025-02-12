<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2022 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Services;

use Elabftw\Enums\Action;
use Elabftw\Exceptions\IllegalActionException;
use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Models\Config;
use Elabftw\Models\Users;

class UserCreatorTest extends \PHPUnit\Framework\TestCase
{
    private UserCreator $UserCreator;

    protected function setUp(): void
    {
        $this->UserCreator = new UserCreator(new Users(1, 1), array(
            'team' => 1,
            'email' => 'livelongandprosper@vulcan.gov.vn',
            'firstname' => 'Leonard',
            'lastname' => 'Nimoy',
            'usergroup' => 4,
        ));
    }

    protected function tearDown(): void
    {
        $Config = Config::getConfig();
        $Config->patch(Action::Update, array('admins_create_users' => '1'));
    }

    public function testCreate(): void
    {
        $this->assertIsInt($this->UserCreator->create());
    }

    public function testCreateFromAdminUser(): void
    {
        $UserCreator = new UserCreator(new Users(4, 2), array(
            'team' => 2,
            'email' => 'praisetheprophets@staff.ds9.bjr',
            'firstname' => 'Kira',
            'lastname' => 'Nerys',
            'usergroup' => 4,
        ));
        $this->assertIsInt($UserCreator->create());
    }

    public function testCreateSysadminFromAdminUser(): void
    {
        $UserCreator = new UserCreator(new Users(4, 2), array(
            'team' => 2,
            'email' => 'vic@holodeck.ds9.bjr',
            'firstname' => 'Vic',
            'lastname' => 'Fontaine',
            'usergroup' => 1,
        ));
        $this->expectException(ImproperActionException::class);
        $UserCreator->create();
    }

    public function testCreateButItIsDisabled(): void
    {
        $Config = Config::getConfig();
        $Config->patch(Action::Update, array('admins_create_users' => '0'));
        $UserCreator = new UserCreator(new Users(4, 2), array(
            'team' => 2,
            'email' => 'vic@holodeck.ds9.bjr',
            'firstname' => 'Vic',
            'lastname' => 'Fontaine',
            'usergroup' => 1,
        ));
        $this->expectException(IllegalActionException::class);
        $UserCreator->create();
    }
}
