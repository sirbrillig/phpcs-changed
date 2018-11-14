<?php
declare(strict_types=1);

namespace PhpcsDiff;

use PhpcsDiff\DiffLineType;

class DiffLine {
	private $oldLine = null;
	private $newLine = null;
	private $type = null;

	public function __construct(int $oldLine, int $newLine, DiffLineType $type) {
		$this->type = $type;
		if (! $type->isAdd()) {
			$this->oldLine = $oldLine;
		}
		if (! $type->isRemove()) {
			$this->newLine = $newLine;
		}
	}

	public function getOldLineNumber(): ?int {
		return $this->oldLine;
	}

	public function getNewLineNumber(): ?int {
		return $this->newLine;
	}
}
