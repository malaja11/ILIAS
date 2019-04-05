<?php
declare(strict_types=1);

/* Copyright (c) 2019 Nils Haagen <nils.haagen@concepts-and-training.de> Extended GPL, see docs/LICENSE */

namespace ILIAS\UI\Implementation\Component\Menu;

use ILIAS\UI\Component\Menu as IMenu;
use ILIAS\UI\Implementation\Component\JavaScriptBindable;

/**
 * Drilldown Menu Control
 */
class Drilldown extends LabeledMenu implements IMenu\Drilldown
{
	use JavaScriptBindable;

	/**
	 * @var \ILIAS\UI\Component\Icon\Icon | \ILIAS\UI\Component\Glyph\Glyph
	 */
	protected $back_icon;

	public function __construct($label, array $items)
	{
		$this->checkLabelParameter($label);
		$this->checkItemParameter($items);
		$this->label = $label;
		$this->items = $items;
	}
}