<?php
/**
 * @copyright Copyright (c) 2016, Roeland Jago Douma <roeland@famdouma.nl>
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
namespace OCA\DAV\CalDAV\Schedule;

use Sabre\DAV\INode;
use Sabre\DAV\PropFind;
use Sabre\DAV\Server;
use Sabre\DAV\Sharing;
use Sabre\DAV\Xml\Property\LocalHref;
use Sabre\DAVACL\IPrincipal;

class Plugin extends \Sabre\CalDAV\Schedule\Plugin {

	/**
	 * Initializes the plugin
	 *
	 * @param Server $server
	 * @return void
	 */
	function initialize(Server $server) {
		parent::initialize($server);
		$server->on('propFind', [$this, 'propFind']);
	}

	/**
	 * Returns a list of addresses that are associated with a principal.
	 *
	 * @param string $principal
	 * @return array
	 */
	protected function getAddressesForPrincipal($principal) {
		$result = parent::getAddressesForPrincipal($principal);

		if ($result === null) {
			$result = [];
		}

		return $result;
	}

	/**
	 * This method handler is invoked during fetching of properties.
	 *
	 * We use this event to add calendar-auto-schedule-specific properties.
	 *
	 * @param PropFind $propFind
	 * @param INode $node
	 * @return void
	 */
	function propFind(PropFind $propFind, INode $node) {
		if ($node instanceof IPrincipal) {
			$caldavPlugin = $this->server->getPlugin('caldav');
			$principalUrl = $node->getPrincipalUrl();

			$propFind->handle('{' . self::NS_CALDAV . '}schedule-default-calendar-URL', function() use ($principalUrl, $caldavPlugin) {

				// We don't support customizing this property yet, so in the
				// meantime we just grab the first calendar in the home-set.
				$calendarHomePath = $caldavPlugin->getCalendarHomeForPrincipal($principalUrl);

				if (!$calendarHomePath) {
					return null;
				}

				$sccs = '{' . self::NS_CALDAV . '}supported-calendar-component-set';

				$result = $this->server->getPropertiesForPath($calendarHomePath, [
					'{DAV:}resourcetype',
					'{DAV:}share-access',
					'{http://owncloud.org/ns}read-only',
					$sccs,
				], 1);

				foreach ($result as $child) {
					if (!isset($child[200]['{DAV:}resourcetype']) || !$child[200]['{DAV:}resourcetype']->is('{' . self::NS_CALDAV . '}calendar')) {
						// Node is either not a calendar
						continue;
					}

					if (isset($child[200]['{http://owncloud.org/ns}read-only']) && $child[200]['{http://owncloud.org/ns}read-only'] === 1) {
						// Read only calendar, mostlikely the birthday calendar
						continue;
					}

					if (isset($child[200]['{DAV:}share-access'])) {
						$shareAccess = $child[200]['{DAV:}share-access']->getValue();
						if ($shareAccess !== Sharing\Plugin::ACCESS_NOTSHARED && $shareAccess !== Sharing\Plugin::ACCESS_SHAREDOWNER) {
							// Node is a shared node, not owned by the relevant
							// user.
							continue;
						}

					}
					if (!isset($child[200][$sccs]) || in_array('VEVENT', $child[200][$sccs]->getValue())) {
						// Either there is no supported-calendar-component-set
						// (which is fine) or we found one that supports VEVENT.
						return new LocalHref($child['href'] . $child['href']);
					}
				}

			});
		}

		parent::propFind($propFind, $node);
	}
}
