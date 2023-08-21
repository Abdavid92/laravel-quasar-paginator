<?php

namespace Abdavid92\LaravelQuasarPaginator\Tests;

use Abdavid92\LaravelQuasarPaginator\DataTablePaginator;
use Illuminate\Foundation\Auth\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

/**
 * @author Abel David.
 */
class DataTablePaginatorTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Setup the test environment.
     *
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $user = new User();
        $user->name = 'Admin';
        $user->email = 'admin@gmail.com';
        $user->password = Hash::make('12345678');

        $user->save();
    }

    /**
     * @return void
     */
    public function testMakePaginator(): void
    {
        $paginator = (new DataTablePaginator(User::query()))
            ->customColumn('name_with_email', function (User $user) {
                return $user->name.' ('.$user->email.')';
            })
            ->filter(function (array $data) {
                return false;
            });

        $count = $paginator->count();

        $this->assertNotEquals(0, $count);

        $data = $paginator->toArray();

        $this->assertNotEmpty($data);
    }
}