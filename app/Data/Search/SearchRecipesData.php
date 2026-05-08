<?php

namespace App\Data\Search;

use Spatie\LaravelData\Data;

final class SearchRecipesData extends Data
{
    public function __construct(
        public readonly ?string $q = null,
        public readonly ?string $glass = null,
        public ?float $abvMin = null {
            set(?float $value) {
                if ($value !== null && ($value < 0 || $value > 100)) {
                    throw new \InvalidArgumentException(
                        "abvMin must be in [0, 100], got {$value}",
                    );
                }
                $this->abvMin = $value;
            }
        },
        public ?float $abvMax = null {
            set(?float $value) {
                if ($value !== null && ($value < 0 || $value > 100)) {
                    throw new \InvalidArgumentException(
                        "abvMax must be in [0, 100], got {$value}",
                    );
                }
                $this->abvMax = $value;
            }
        },
        public ?int $volMin = null {
            set(?int $value) {
                if ($value !== null && ($value < 0 || $value > 500)) {
                    throw new \InvalidArgumentException(
                        "volMin must be in [0, 500], got {$value}",
                    );
                }
                $this->volMin = $value;
            }
        },
        public ?int $volMax = null {
            set(?int $value) {
                if ($value !== null && ($value < 0 || $value > 500)) {
                    throw new \InvalidArgumentException(
                        "volMax must be in [0, 500], got {$value}",
                    );
                }
                $this->volMax = $value;
            }
        },
        public readonly ?string $tag = null,
        public readonly int $page = 1,
        public readonly int $perPage = 15,
    ) {}
}
