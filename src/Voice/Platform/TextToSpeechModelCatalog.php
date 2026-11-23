<?php

declare(strict_types=1);

namespace App\Voice\Platform;

use Symfony\AI\Platform\Bridge\Generic\TextToSpeechModel;
use Symfony\AI\Platform\Capability;
use Symfony\AI\Platform\Model;
use Symfony\AI\Platform\ModelCatalog\AbstractModelCatalog;

/**
 * Every model name is a speech model: cast profiles pick the VoiceStudio engine per character
 * (kittentts, omnivoice, mlx-audio, ...), so the catalog cannot list them up front.
 */
final class TextToSpeechModelCatalog extends AbstractModelCatalog
{
    public function __construct()
    {
        $this->models = [];
    }

    public function getModel(string $modelName): Model
    {
        $parsed = $this->parseModelName($modelName);

        return new TextToSpeechModel(
            $parsed['name'],
            [Capability::INPUT_TEXT, Capability::OUTPUT_AUDIO, Capability::TEXT_TO_SPEECH],
            $parsed['options'],
        );
    }
}
