<?php

namespace Rector\Core\Tests\Issues\LocallyThis\Fixture;

class Fixture
{
    public function run()
    {
        self::someStatic();
    }

    private static function someStatic()
    {
    }
}

?>
-----
<?php

namespace Rector\Core\Tests\Issues\LocallyThis\Fixture;

class Fixture
{
    public function run()
    {
        $this->someStatic();
    }

    private function someStatic()
    {
    }
}

?>
