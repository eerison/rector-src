<?php

namespace Rector\Core\Tests\Issues\EmptyBooleanCompare\Fixture;

final class AlwaysExistsArrayDimFetchItem
{
    public function checkUrl(string $url)
    {
        if (rand(0, 1)) {
            $parts = [
                'host' => 'foo',
            ];
        } else {
            $parts = [
                'host' => '',
            ];
        }

        if (!empty($parts['host'])) {
            return $parts['host'];
        }

        return null;
    }
}

?>
-----
<?php

namespace Rector\Core\Tests\Issues\EmptyBooleanCompare\Fixture;

final class AlwaysExistsArrayDimFetchItem
{
    public function checkUrl(string $url)
    {
        if (rand(0, 1) !== 0) {
            $parts = [
                'host' => 'foo',
            ];
        } else {
            $parts = [
                'host' => '',
            ];
        }

        if (isset($parts['host']) && $parts['host'] !== '') {
            return $parts['host'];
        }

        return null;
    }
}

?>
