<?php

use Redaxo\Core\Backend\Controller;
use Redaxo\Core\View\View;

/** @var Redaxo\Core\Addon\Addon $this */

echo View::title($this->i18n('title'));

Controller::includeCurrentPageSubPath();
