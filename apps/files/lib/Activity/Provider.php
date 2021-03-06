<?php
/**
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Files\Activity;

use OCP\Activity\IEvent;
use OCP\Activity\IManager;
use OCP\Activity\IProvider;
use OCP\IL10N;
use OCP\IURLGenerator;

class Provider implements IProvider {

	/** @var IL10N */
	protected $l;

	/** @var IURLGenerator */
	protected $url;

	/** @var IManager */
	protected $activityManager;

	/**
	 * @param IL10N $l
	 * @param IURLGenerator $url
	 * @param IManager $activityManager
	 */
	public function __construct(IL10N $l, IURLGenerator $url, IManager $activityManager) {
		$this->l = $l;
		$this->url = $url;
		$this->activityManager = $activityManager;
	}

	/**
	 * @param IEvent $event
	 * @param IEvent|null $previousEvent
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parse(IEvent $event, IEvent $previousEvent = null) {
		if ($event->getApp() !== 'files') {
			throw new \InvalidArgumentException();
		}

		if ($previousEvent instanceof IEvent && $event->getSubject() !== $previousEvent->getSubject()) {
			// Different subject means not the same string, so no grouping
			$previousEvent = null;
		}

		if ($this->activityManager->isFormattingFilteredObject()) {
			try {
				return $this->parseShortVersion($event);
			} catch (\InvalidArgumentException $e) {
				// Ignore and simply use the long version...
			}
		}

		return $this->parseLongVersion($event);
	}

	/**
	 * @param IEvent $event
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parseShortVersion(IEvent $event) {
		$parsedParameters = $this->getParsedParameters($event->getSubject(), $event->getSubjectParameters());
		$richParameters = $this->getRichParameters($event->getSubject(), $event->getSubjectParameters());

		if ($event->getSubject() === 'created_by') {
			$event->setParsedSubject($this->l->t('Created by %s', [$parsedParameters[1]]))
				->setRichSubject($this->l->t('Created by {user1}'), ['user1' => $richParameters['user1']])
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('files', 'add-color.svg')));
		} else if ($event->getSubject() === 'changed_by') {
			$event->setParsedSubject($this->l->t('Changed by %2$s', [$parsedParameters[1]]))
				->setRichSubject($this->l->t('Changed by {user1}'), ['user1' => $richParameters['user1']])
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('files', 'change.svg')));
		} else if ($event->getSubject() === 'deleted_by') {
			$event->setParsedSubject($this->l->t('Deleted by %2$s', [$parsedParameters[1]]))
				->setRichSubject($this->l->t('Deleted by {user1}'), ['user1' => $richParameters['user1']])
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('files', 'delete-color.svg')));
		} else if ($event->getSubject() === 'restored_by') {
			$event->setParsedSubject($this->l->t('Restored by %2$s', [$parsedParameters[1]]))
				->setRichSubject($this->l->t('Restored by {user1}'), ['user1' => $richParameters['user1']]);
		} else if ($event->getSubject() === 'renamed_by') {
			$event->setParsedSubject($this->l->t('Renamed by %2$s', [$parsedParameters[1]]))
				->setRichSubject($this->l->t('Renamed by {user1}'), ['user1' => $richParameters['user1']])
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('files', 'change.svg')));
		} else if ($event->getSubject() === 'moved_by') {
			$event->setParsedSubject($this->l->t('Moved by %2$s', [$parsedParameters[1]]))
				->setRichSubject($this->l->t('Moved by {user1}'), ['user1' => $richParameters['user1']])
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('files', 'change.svg')));
		} else {
			throw new \InvalidArgumentException();
		}

		return $event;
	}

	/**
	 * @param IEvent $event
	 * @return IEvent
	 * @throws \InvalidArgumentException
	 * @since 11.0.0
	 */
	public function parseLongVersion(IEvent $event) {
		$parsedParameters = $this->getParsedParameters($event->getSubject(), $event->getSubjectParameters());
		$richParameters = $this->getRichParameters($event->getSubject(), $event->getSubjectParameters());

		if ($event->getSubject() === 'created_self') {
			$event->setParsedSubject($this->l->t('You created %1$s', $parsedParameters))
				->setRichSubject($this->l->t('You created {file1}'), $richParameters)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('files', 'add-color.svg')));
		} else if ($event->getSubject() === 'created_by') {
			$event->setParsedSubject($this->l->t('%2$s created %1$s', $parsedParameters))
				->setRichSubject($this->l->t('{user1} created {file1}'), $richParameters)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('files', 'add-color.svg')));
		} else if ($event->getSubject() === 'created_public') {
			$event->setParsedSubject($this->l->t('%1$s was created in a public folder', $parsedParameters))
				->setRichSubject($this->l->t('{file1} was created in a public folder'), $richParameters)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('files', 'add-color.svg')));
		} else if ($event->getSubject() === 'changed_self') {
			$event->setParsedSubject($this->l->t('You changed %1$s', $parsedParameters))
				->setRichSubject($this->l->t('You changed {file1}'), $richParameters)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('files', 'change.svg')));
		} else if ($event->getSubject() === 'changed_by') {
			$event->setParsedSubject($this->l->t('%2$s changed %1$s', $parsedParameters))
				->setRichSubject($this->l->t('{user1} changed {file1}'), $richParameters)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('files', 'change.svg')));
		} else if ($event->getSubject() === 'deleted_self') {
			$event->setParsedSubject($this->l->t('You deleted %1$s', $parsedParameters))
				->setRichSubject($this->l->t('You deleted {file1}'), $richParameters)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('files', 'delete-color.svg')));
		} else if ($event->getSubject() === 'deleted_by') {
			$event->setParsedSubject($this->l->t('%2$s deleted %1$s', $parsedParameters))
				->setRichSubject($this->l->t('{user1} deleted {file1}'), $richParameters)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('files', 'delete-color.svg')));
		} else if ($event->getSubject() === 'restored_self') {
			$event->setParsedSubject($this->l->t('You restored %1$s', $parsedParameters))
				->setRichSubject($this->l->t('You restored {file1}'), $richParameters);
		} else if ($event->getSubject() === 'restored_by') {
			$event->setParsedSubject($this->l->t('%2$s restored %1$s', $parsedParameters))
				->setRichSubject($this->l->t('{user1} restored {file1}'), $richParameters);
		} else if ($event->getSubject() === 'renamed_self') {
			$event->setParsedSubject($this->l->t('You renamed %2$s to %1$s', $parsedParameters))
				->setRichSubject($this->l->t('You renamed {file2} to {file1}'), $richParameters)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('files', 'change.svg')));
		} else if ($event->getSubject() === 'renamed_by') {
			$event->setParsedSubject($this->l->t('%2$s renamed %3$s to %1$s', $parsedParameters))
				->setRichSubject($this->l->t('{user1} renamed {file2} to {file1}'), $richParameters)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('files', 'change.svg')));
		} else if ($event->getSubject() === 'moved_self') {
			$event->setParsedSubject($this->l->t('You moved %2$s to %1$s', $parsedParameters))
				->setRichSubject($this->l->t('You moved {file2} to {file1}'), $richParameters)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('files', 'change.svg')));
		} else if ($event->getSubject() === 'moved_by') {
			$event->setParsedSubject($this->l->t('%2$s moved %3$s to %1$s', $parsedParameters))
				->setRichSubject($this->l->t('{user1} moved {file2} to {file1}'), $richParameters)
				->setIcon($this->url->getAbsoluteURL($this->url->imagePath('files', 'change.svg')));
		} else {
			throw new \InvalidArgumentException();
		}

		return $event;
	}

	protected function getParsedParameters($subject, array $parameters) {
		switch ($subject) {
			case 'created_self':
			case 'created_public':
			case 'changed_self':
			case 'deleted_self':
			case 'restored_self':
			return [
				array_shift($parameters[0]),
			];
			case 'created_by':
			case 'changed_by':
			case 'deleted_by':
			case 'restored_by':
				return [
					array_shift($parameters[0]),
					$parameters[1],
				];
			case 'renamed_self':
			case 'moved_self':
				return [
					array_shift($parameters[0]),
					array_shift($parameters[1]),
				];
			case 'renamed_by':
			case 'moved_by':
				return [
					array_shift($parameters[0]),
					$parameters[1],
					array_shift($parameters[2]),
				];
		}
		return [];
	}

	protected function getRichParameters($subject, array $parameters) {
		switch ($subject) {
			case 'created_self':
			case 'created_public':
			case 'changed_self':
			case 'deleted_self':
			case 'restored_self':
				return [
					'file1' => $this->getRichFileParameter($parameters[0]),
				];
			case 'created_by':
			case 'changed_by':
			case 'deleted_by':
			case 'restored_by':
				return [
					'file1' => $this->getRichFileParameter($parameters[0]),
					'user1' => $this->getRichUserParameter($parameters[1]),
				];
			case 'renamed_self':
			case 'moved_self':
				return [
					'file1' => $this->getRichFileParameter($parameters[0]),
					'file2' => $this->getRichFileParameter($parameters[1]),
				];
			case 'renamed_by':
			case 'moved_by':
				return [
					'file1' => $this->getRichFileParameter($parameters[0]),
					'user1' => $this->getRichUserParameter($parameters[1]),
					'file2' => $this->getRichFileParameter($parameters[2]),
				];
		}
		return [];
	}

	protected function getRichFileParameter($parameter) {
		$path = reset($parameter);
		$id = key($parameter);
		return [
			'type' => 'file',
			'id' => $id,
			'name' => basename($path),
			'path' => $path,
		];
	}

	protected function getRichUserParameter($parameter) {
		return [
			'type' => 'user',
			'id' => $parameter,
			'name' => $parameter,// FIXME Use display name
		];
	}
}
