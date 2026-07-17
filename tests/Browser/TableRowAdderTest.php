<?php

namespace A17\Twill\Tests\Browser;

use Laravel\Dusk\Browser;

class TableRowAdderTest extends BrowserTestCase
{
    public ?string $example = 'tests-modules';

    public function testCanInsertEntryBetweenRowsInFlatListing(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->superAdmin, 'twill_users');
            $browser->visitTwill();

            $this->createAuthor($browser, 'Author Alpha');
            $this->createAuthor($browser, 'Author Charlie');

            $browser->visit('/twill/personnel/authors');
            $browser->waitForText('Author Charlie');

            // Reveal the row adder between the two rows and click it.
            $browser->mouseover('.datatable__drag tr:nth-child(2) .tableRowAdder');
            $browser->click('.datatable__drag tr:nth-child(2) .tableRowAdder__button');

            $browser->waitFor('.modal__header');
            $browser->type('name[en]', 'Author Bravo');
            $browser->press('Create');

            // Inserting keeps us on the listing instead of redirecting to the edit form.
            $browser->waitForText('Author Bravo');
            $browser->assertPathIs('/twill/personnel/authors');

            $browser->waitUsing(10, 100, function () use ($browser) {
                return str_contains($browser->text('.datatable__drag tr:nth-child(2)'), 'Author Bravo');
            }, 'The created entry was not inserted between the existing rows.');

            $browser->assertSeeIn('.datatable__drag tr:nth-child(1)', 'Author Alpha');
            $browser->assertSeeIn('.datatable__drag tr:nth-child(2)', 'Author Bravo');
            $browser->assertSeeIn('.datatable__drag tr:nth-child(3)', 'Author Charlie');
        });

        $names = \App\Models\Author::ordered()->get()->map(fn ($author) => $author->name)->toArray();

        $this->assertEquals(['Author Alpha', 'Author Bravo', 'Author Charlie'], $names);
    }

    public function testCanInsertEntryAtTopOfListing(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->superAdmin, 'twill_users');
            $browser->visitTwill();

            $this->createAuthor($browser, 'Author Bravo');

            $browser->visit('/twill/personnel/authors');
            $browser->waitForText('Author Bravo');

            // The first row adder must not be clipped by the table scroller.
            $notClipped = $browser->script(
                'var b = document.querySelector(".datatable__drag tr:first-child .tableRowAdder__button").getBoundingClientRect();' .
                'var s = document.querySelector(".datatable__table .table__scroller").getBoundingClientRect();' .
                'return b.top >= s.top && b.height > 0'
            )[0];

            $this->assertTrue($notClipped, 'The first row adder is clipped by the table scroller.');

            $browser->mouseover('.datatable__drag tr:first-child .tableRowAdder');
            $browser->click('.datatable__drag tr:first-child .tableRowAdder__button');

            $browser->waitFor('.modal__header');
            $browser->type('name[en]', 'Author Alpha');
            $browser->press('Create');

            $browser->waitForText('Author Alpha');

            $browser->waitUsing(10, 100, function () use ($browser) {
                return str_contains($browser->text('.datatable__drag tr:nth-child(1)'), 'Author Alpha');
            }, 'The created entry was not inserted at the top of the listing.');
        });

        $names = \App\Models\Author::ordered()->get()->map(fn ($author) => $author->name)->toArray();

        $this->assertEquals(['Author Alpha', 'Author Bravo'], $names);
    }

    public function testCreateAnotherKeepsInsertingBelowThePreviousEntry(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->superAdmin, 'twill_users');
            $browser->visitTwill();

            $this->createAuthor($browser, 'Author Alpha');
            $this->createAuthor($browser, 'Author Delta');

            $browser->visit('/twill/personnel/authors');
            $browser->waitForText('Author Delta');

            $browser->mouseover('.datatable__drag tr:nth-child(2) .tableRowAdder');
            $browser->click('.datatable__drag tr:nth-child(2) .tableRowAdder__button');

            $browser->waitFor('.modal__header');
            $browser->type('name[en]', 'Author Bravo');
            $browser->press('Create and add another');

            $browser->waitForText('Author Bravo');
            $browser->type('name[en]', 'Author Charlie');
            $browser->press('Create');

            $browser->waitForText('Author Charlie');
            $browser->assertPathIs('/twill/personnel/authors');

            $browser->waitUsing(10, 100, function () use ($browser) {
                return str_contains($browser->text('.datatable__drag tr:nth-child(3)'), 'Author Charlie');
            }, 'The second created entry was not inserted below the first one.');
        });

        $names = \App\Models\Author::ordered()->get()->map(fn ($author) => $author->name)->toArray();

        $this->assertEquals(['Author Alpha', 'Author Bravo', 'Author Charlie', 'Author Delta'], $names);
    }

    public function testCanInsertEntryBetweenNestedItems(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->superAdmin, 'twill_users');
            $browser->visitTwill();

            $browser->createModuleEntryWithTitle('Categories', 'Parent');
            $browser->createModuleEntryWithTitle('Categories', 'Child One');
            $browser->createModuleEntryWithTitle('Categories', 'Child Two');

            // Nest both children under the parent.
            $parent = $this->categoryByTitle('Parent');

            \App\Models\Category::saveTreeFromIds([
                [
                    'id' => $parent->id,
                    'children' => [
                        ['id' => $this->categoryByTitle('Child One')->id, 'children' => []],
                        ['id' => $this->categoryByTitle('Child Two')->id, 'children' => []],
                    ],
                ],
            ]);

            $browser->visit('/twill/categories');
            $browser->waitForText('Child Two');

            // Reveal the row adder above the second child and click it.
            $childTwoItem = '.nested__dropArea .nested__dropArea > li:nth-child(2) > .nested-item';
            $browser->mouseover("$childTwoItem .tableRowAdder");
            $browser->click("$childTwoItem .tableRowAdder__button");

            $browser->waitFor('.modal__header');
            $browser->type('title[en]', 'Child New');
            $browser->press('Create');

            $browser->waitForText('Child New');
            $browser->assertPathIs('/twill/categories');

            $browser->waitUsing(10, 100, function () use ($browser, $childTwoItem) {
                return str_contains($browser->text($childTwoItem), 'Child New');
            }, 'The created entry was not inserted between the existing nested items.');

            $browser->assertSeeIn('.nested__dropArea .nested__dropArea > li:nth-child(1)', 'Child One');
            $browser->assertSeeIn('.nested__dropArea .nested__dropArea > li:nth-child(3)', 'Child Two');
        });

        $parent = $this->categoryByTitle('Parent');
        $childNew = $this->categoryByTitle('Child New');

        $this->assertEquals($parent->id, $childNew->parent_id);

        $childTitles = $parent->children()
            ->orderBy('position')
            ->get()
            ->map(fn ($category) => $category->title)
            ->toArray();

        $this->assertEquals(['Child One', 'Child New', 'Child Two'], $childTitles);
    }

    public function testCanInsertChildIntoItemWithoutChildren(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->loginAs($this->superAdmin, 'twill_users');
            $browser->visitTwill();

            $browser->createModuleEntryWithTitle('Categories', 'Solo Parent');

            $browser->visit('/twill/categories');
            $browser->waitForText('Solo Parent');

            // The item has no children: its empty drop area offers a child adder.
            $childAdder = '.nested-datatable__item > .nested__dropArea--empty .tableRowAdder';
            $browser->mouseover($childAdder);
            $browser->click("$childAdder .tableRowAdder__button");

            $browser->waitFor('.modal__header');
            $browser->type('title[en]', 'First Child');
            $browser->press('Create');

            $browser->waitForText('First Child');
            $browser->assertPathIs('/twill/categories');

            $browser->waitUsing(10, 100, function () use ($browser) {
                return str_contains($browser->text('.nested__dropArea .nested__dropArea'), 'First Child');
            }, 'The created entry was not added as a child of the parent item.');
        });

        $parent = $this->categoryByTitle('Solo Parent');
        $child = $this->categoryByTitle('First Child');

        $this->assertEquals($parent->id, $child->parent_id);
    }

    private function createAuthor(Browser $browser, string $name): void
    {
        $browser->visit('/twill/personnel/authors');
        $browser->waitForText('Add new');
        $browser->press('Add new');

        $browser->waitFor('.modal__header');
        $browser->type('name[en]', $name);
        $browser->press('Create');

        $browser->waitFor('.fieldset__content');
    }

    private function categoryByTitle(string $title): \App\Models\Category
    {
        return \App\Models\Translations\CategoryTranslation::where('title', $title)
            ->where('locale', 'en')
            ->firstOrFail()
            ->category;
    }
}
