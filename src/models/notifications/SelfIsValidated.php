<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2023 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Models\Notifications;

use Elabftw\Enums\Notifications;
use Elabftw\Interfaces\MailableInterface;
use Elabftw\Models\Config;

/**
 * When our account has been validated
 */
class SelfIsValidated extends AbstractNotifications implements MailableInterface
{
    protected Notifications $category = Notifications::SelfIsValidated;

    // Note: here the actor fullname is directly fed to the instance, instead of fetching it from a new Users() like others.
    public function getEmail(): array
    {
        $subject = _('Account validated');
        $url = Config::fromEnv('SITE_URL') . '/login.php';
        $body = _('Hello. Your account on eLabFTW was validated by an admin. Follow this link to login: ') . $url;
        return array('subject' => $subject, 'body' => $body);
    }
}
