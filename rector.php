<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php80\Rector\Switch_\ChangeSwitchToMatchRector;
use Rector\Php80\Rector\Class_\ClassPropertyAssignToConstructorPromotionRector;
use Rector\TypeDeclaration\Rector\ClassMethod\ReturnNeverTypeRector;
use Rector\Php71\Rector\FuncCall\RemoveExtraParametersRector;
use Rector\Php80\Rector\ClassMethod\AddParamBasedOnParentClassMethodRector;

/**
 * Keeps lib/ on modern PHP idioms. Run `composer rector` to apply, CI runs it in
 * dry-run mode. To adopt a newer PHP version later, raise the php set below.
 */
return RectorConfig::configure()
    ->withPaths([__DIR__ . '/lib', __DIR__ . '/bin/dbsteward'])
    ->withPhpSets(php83: true)
    ->withSkip([
        // match uses strict (===) comparison; switch is loose (==). Many switches here
        // compare SimpleXMLElement values, so the rewrite would change behavior.
        ChangeSwitchToMatchRector::class,
        // Calls are routed through runtime class aliases (format::, format_table::...),
        // which static analysis resolves to the wrong signatures.
        RemoveExtraParametersRector::class,
        AddParamBasedOnParentClassMethodRector::class,
        // Adding return/property types would fatal untyped overrides in subclasses.
        ReturnNeverTypeRector::class,
        ClassPropertyAssignToConstructorPromotionRector::class,
    ]);
