<?php

declare(strict_types=1);
/**
 * @copyright Copyright (c) 2023 Vitor Mattos <vitor@php.rio>
 *
 * @author Vitor Mattos <vitor@php.rio>
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
 */

namespace OCA\Libresign\DataObjects;

use OCA\Libresign\Db\FileElement;
use OCA\Libresign\Db\UserElement;

class VisibleElementAssoc {
	/** @var FileElement */
	private $fileElement;
	/** @var UserElement */
	private $userElement;
	/** @var string */
	private $tempFile;

	public function __construct(FileElement $fileElement, UserElement $userElement, string $tempFile) {
		$this->fileElement = $fileElement;
		$this->userElement = $userElement;
		$this->tempFile = $tempFile;
	}

	public function getFileElement(): FileElement {
		return $this->fileElement;
	}

	public function getUserElement(): UserElement {
		return $this->userElement;
	}

	public function getTempFile(): string {
		return $this->tempFile;
	}
}
