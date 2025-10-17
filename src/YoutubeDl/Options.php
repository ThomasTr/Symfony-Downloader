<?php declare(strict_types=1);

namespace App\YoutubeDl;

final class Options extends \YoutubeDl\Options
{
    // Preset Aliases
    private ?string $presetAlias = null;

    // Extractor Options
    private bool $forceGenericExtractor = false;

    public function presetAlias(?string $presetAlias): self
    {
        $new = clone $this;
        $new->presetAlias = $presetAlias;

        return $new;
    }

    public function forceGenericExtractor(bool $forceGenericExtractor): self
    {
        $new = clone $this;
        $new->forceGenericExtractor = $forceGenericExtractor;

        return $new;
    }

    public function toArray(): array
    {
        $array = parent::toArray();

        $array['preset-alias'] = $this->presetAlias;

        if(true === $this->forceGenericExtractor)
        {
            $array['force-generic-extractor'] = $this->forceGenericExtractor;
        }

        return $array;
    }

    public static function create(): static
    {
        return new static();
    }
}
