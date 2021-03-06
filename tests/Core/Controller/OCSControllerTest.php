<?php
/**
 * @copyright 2016, Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @author Roeland Jago Douma <roeland@famdouma.nl>
 *
 * @license GNU AGPL version 3 or any later version
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 */
namespace OC\Core\Controller;

use OC\CapabilitiesManager;
use OC\Security\Bruteforce\Throttler;
use OCP\AppFramework\Http\DataResponse;
use OCP\IRequest;
use OCP\IUser;
use OCP\IUserManager;
use OCP\IUserSession;
use Test\TestCase;

class OCSControllerTest extends TestCase {

	/** @var IRequest|\PHPUnit_Framework_MockObject_MockObject */
	private $request;

	/** @var CapabilitiesManager|\PHPUnit_Framework_MockObject_MockObject */
	private $capabilitiesManager;

	/** @var IUserSession|\PHPUnit_Framework_MockObject_MockObject */
	private $userSession;

	/** @var IUserManager|\PHPUnit_Framework_MockObject_MockObject */
	private $userManager;

	/** @var Throttler|\PHPUnit_Framework_MockObject_MockObject */
	private $throttler;

	/** @var OCSController */
	private $controller;

	public function setUp() {
		parent::setUp();

		$this->request = $this->createMock(IRequest::class);
		$this->capabilitiesManager = $this->createMock(CapabilitiesManager::class);
		$this->userSession = $this->createMock(IUserSession::class);
		$this->userManager = $this->createMock(IUserManager::class);
		$this->throttler = $this->createMock(Throttler::class);

		$this->controller = new OCSController(
			'core',
			$this->request,
			$this->capabilitiesManager,
			$this->userSession,
			$this->userManager,
			$this->throttler
		);
	}

	public function testGetConfig() {
		$this->request->method('getServerHost')
			->willReturn('awesomehost.io');

		$data = [
			'version' => '1.7',
			'website' => 'Nextcloud',
			'host' => 'awesomehost.io',
			'contact' => '',
			'ssl' => 'false',
		];

		$expected = new DataResponse($data);
		$this->assertEquals($expected, $this->controller->getConfig());

		return new DataResponse($data);
	}

	public function testGetCapabilities() {
		list($major, $minor, $micro) = \OCP\Util::getVersion();

		$result = [];
		$result['version'] = array(
			'major' => $major,
			'minor' => $minor,
			'micro' => $micro,
			'string' => \OC_Util::getVersionString(),
			'edition' => '',
		);

		$capabilities = [
			'foo' => 'bar',
			'a' => [
				'b' => true,
				'c' => 11,
			]
		];
		$this->capabilitiesManager->method('getCapabilities')
			->willReturn($capabilities);

		$result['capabilities'] = $capabilities;

		$expected = new DataResponse($result);
		$this->assertEquals($expected, $this->controller->getCapabilities());
	}

	public function testGetCurrentUser() {
		$user = $this->createMock(IUser::class);
		$user->method('getUID')->willReturn('uid');
		$user->method('getDisplayName')->willReturn('displayName');
		$user->method('getEMailAddress')->willReturn('e@mail.com');


		$this->userSession->method('getUser')
			->willReturn($user);

		$expected = new DataResponse([
			'id' => 'uid',
			'display-name' => 'displayName',
			'email' => 'e@mail.com',
		]);
		$this->assertEquals($expected, $this->controller->getCurrentUser());
	}

	public function testPersonCheckValid() {
		$this->request->method('getRemoteAddress')
			->willReturn('1.2.3.4');

		$this->throttler->expects($this->once())
			->method('sleepDelay')
			->with('1.2.3.4');

		$this->throttler->expects($this->never())
			->method('registerAttempt');

		$this->userManager->method('checkPassword')
			->with(
				$this->equalTo('user'),
				$this->equalTo('pass')
			)->willReturn($this->createMock(IUser::class));

		$expected = new DataResponse([
			'person' => [
				'personid' => 'user'
			]
		]);

		$this->assertEquals($expected, $this->controller->personCheck('user', 'pass'));
	}

	public function testPersonInvalid() {
		$this->request->method('getRemoteAddress')
			->willReturn('1.2.3.4');

		$this->throttler->expects($this->once())
			->method('sleepDelay')
			->with('1.2.3.4');

		$this->throttler->expects($this->once())
			->method('registerAttempt')
			->with(
				$this->equalTo('login'),
				$this->equalTo('1.2.3.4')
			);

		$this->userManager->method('checkPassword')
			->with(
				$this->equalTo('user'),
				$this->equalTo('wrongpass')
			)->willReturn(false);

		$expected = new DataResponse(null, 102);

		$this->assertEquals($expected, $this->controller->personCheck('user', 'wrongpass'));
	}

	public function testPersonNoLogin() {
		$this->request->method('getRemoteAddress')
			->willReturn('1.2.3.4');

		$this->throttler->expects($this->never())
			->method('sleepDelay');

		$this->throttler->expects($this->never())
			->method('registerAttempt');

		$this->userManager->method('checkPassword')
			->with(
				$this->equalTo('user'),
				$this->equalTo('wrongpass')
			)->willReturn(false);

		$expected = new DataResponse(null, 101);

		$this->assertEquals($expected, $this->controller->personCheck('', ''));
	}
}
