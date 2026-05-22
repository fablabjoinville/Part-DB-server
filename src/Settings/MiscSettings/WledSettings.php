<?php

declare(strict_types=1);

namespace App\Settings\MiscSettings;

use App\Settings\SettingsIcon;
use Jbtronics\SettingsBundle\Settings\Settings;
use Jbtronics\SettingsBundle\Settings\SettingsParameter;
use Jbtronics\SettingsBundle\Settings\SettingsTrait;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Translation\TranslatableMessage as TM;
use Symfony\Component\Validator\Constraints as Assert;

#[Settings(label: new TM("settings.misc.wled"))]
#[SettingsIcon("fa-lightbulb")]
class WledSettings
{
    use SettingsTrait;

    #[SettingsParameter(
        label: new TM("settings.misc.wled.color"),
        description: new TM("settings.misc.wled.color.help"),
        formOptions: ['attr' => ['type' => 'color']],
    )]
    public string $highlightColor = '#FF6600';

    #[SettingsParameter(
        label: new TM("settings.misc.wled.duration"),
        description: new TM("settings.misc.wled.duration.help"),
        formOptions: ['attr' => ['min' => 1, 'max' => 255]],
    )]
    #[Assert\Range(min: 1, max: 255)]
    public int $highlightDurationS = 30;

    #[SettingsParameter(
        label: new TM("settings.misc.wled.effect"),
        description: new TM("settings.misc.wled.effect.help"),
        formOptions: ['attr' => ['min' => 0, 'max' => 186]],
    )]
    #[Assert\Range(min: 0, max: 186)]
    public int $highlightEffect = 1;

    #[SettingsParameter(
        label: new TM("settings.misc.wled.host"),
        description: new TM("settings.misc.wled.host.help"),
    )]
    public string $wledHost = '';

    #[SettingsParameter(
        label: new TM("settings.misc.wled.rowConfig"),
        description: new TM("settings.misc.wled.rowConfig.help"),
        formType: TextareaType::class,
    )]
    public string $rowConfig = '{"F":{"y":0,"perDrawer":1},"E":{"y":1,"perDrawer":1},"D":{"y":2,"perDrawer":1},"C":{"y":3,"perDrawer":1},"B":{"y":4,"perDrawer":2},"A":{"y":5,"perDrawer":5}}';
}
