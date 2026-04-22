<?php

declare(strict_types=1);

namespace modules\bozp;

use Craft;
use yii\base\Module as BaseModule;

/**
 * BOZP Permits Module
 *
 * Work permit (povolenie na prácu) lifecycle for Mondelez SR Production.
 * Handles general permits (GPTW) in v1; high-risk sub-permits in a later phase.
 */
class Module extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@modules/bozp', __DIR__);

        $this->controllerNamespace = Craft::$app->getRequest()->getIsConsoleRequest()
            ? 'modules\\bozp\\console\\controllers'
            : 'modules\\bozp\\controllers';

        parent::init();

        Craft::info('BOZP module loaded.', __METHOD__);
    }
}