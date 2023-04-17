<?php declare(strict_types=1);
/**
 * @author Nicolas CARPi <nico-git@deltablot.email>
 * @copyright 2023 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

namespace Elabftw\Services;

use Defuse\Crypto\Crypto;
use Defuse\Crypto\Key;
use Elabftw\Models\Config;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

class EairefRemoteDirectoryTest extends \PHPUnit\Framework\TestCase
{
    public function testSearch(): void
    {
        $mockResponse = array(
            array(
                '_id' => 'adele.goldberg',
                'mail' => 'adele.goldberg@parc.example.net',
                'sn' => 'Goldberg',
                'givenname' => 'Adele',
            ),
            array(
                '_id' => 'anita.borg',
                'mail' => 'anita.borg@parc.example.net',
                'sn' => 'Borg',
                'givenname' => 'Anita',
                'accountStatus' => 'AccountLockedBecauseIsASpy',
            ),
        );
        $mock = new MockHandler(array(
            new Response(200, array(), (string) json_encode($mockResponse)),
            new Response(204, array(), ''),
        ));
        $handlerStack = HandlerStack::create($mock);
        $client = new Client(array('handler' => $handlerStack));

        $config = array(
            array(
                'url' => 'https://directory.example.fr',
                'auth' => array('unit42', 'secr3tpassword'),
                'email' => 'mail',
                'orgid' => '_id',
                'disabled' => array(
                    array(
                        'value' => 'AccountLockedBecauseIsASpy',
                        'property' => 'accountStatus',
                    ),
                    array(
                        'value' => 'AccountTerminatedBecauseIsARobot',
                        'property' => 'accountStatus',
                    ),
                ),
                'lastname' => 'sn',
                'firstname' => 'givenname',
                'preg_quote' => true,
            ),
        );

        $config = Crypto::encrypt(json_encode($config, JSON_THROW_ON_ERROR), Key::loadFromAsciiSafeString(Config::fromEnv('SECRET_KEY')));
        $RemoteDir = new EairefRemoteDirectory($client, $config);
        $res = $RemoteDir->search('parc');

        $this->assertEquals('Adele', $res[0]['firstname']);
        $this->assertEquals('Goldberg', $res[0]['lastname']);
        $this->assertEquals('adele.goldberg@parc.example.net', $res[0]['email']);
        $this->assertTrue($res[1]['disabled']);

        // empty results search query
        $res = $RemoteDir->search('cowabunga');
        $this->assertEmpty($res);
    }
}