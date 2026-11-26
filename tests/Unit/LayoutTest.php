<?php

declare(strict_types=1);

namespace PlayDuo\Tests\Unit;

use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../includes/functions.php';

final class LayoutTest extends TestCase
{
    /** @param array<string, mixed> $vars */
    private static function renderHeader(array $vars): string
    {
        extract($vars);
        ob_start();
        include __DIR__ . '/../../includes/header.php';
        return (string) ob_get_clean();
    }

    public function testHeaderEscapesTitleAndMessage(): void
    {
        $html = self::renderHeader([
            'page_title' => '<会員登録>', 'login_member' => null, 'message' => '<b>完了</b>', 'csrf_token' => 't',
        ]);

        self::assertStringContainsString('<title>&lt;会員登録&gt; | Play Duo</title>', $html);
        self::assertStringContainsString('&lt;b&gt;完了&lt;/b&gt;', $html);
    }

    public function testHeaderShowsLoginAndRegisterLinksForGuest(): void
    {
        $html = self::renderHeader(['page_title' => 'x', 'login_member' => null, 'message' => null, 'csrf_token' => 't']);

        self::assertStringContainsString('href="login.php"', $html);
        self::assertStringContainsString('href="register.php"', $html);
        self::assertStringNotContainsString('logout.php', $html);
    }

    public function testHeaderShowsMypageAndLogoutFormForMember(): void
    {
        $html = self::renderHeader([
            'page_title' => 'x', 'login_member' => ['member_id' => 1, 'nickname' => 'デュオ', 'name' => '山田'],
            'message' => null, 'csrf_token' => 'tok"en',
        ]);

        self::assertStringContainsString('href="mypage.php"', $html);
        self::assertStringContainsString('action="logout.php"', $html);
        self::assertStringContainsString('name="csrf_token" value="tok&quot;en"', $html);
        self::assertStringNotContainsString('href="register.php"', $html);
    }

    public function testHeaderShowsSearchBoxAndCartCountOnlyForMember(): void
    {
        $guest = self::renderHeader(['page_title' => 'x', 'login_member' => null, 'message' => null, 'csrf_token' => 't']);
        $member = self::renderHeader([
            'page_title' => 'x', 'login_member' => ['member_id' => 1], 'message' => null, 'csrf_token' => 't', 'cart_count' => 3,
        ]);

        self::assertStringContainsString('action="search.php"', $guest);
        self::assertStringContainsString('name="keyword"', $guest);
        self::assertStringContainsString('href="cart.php"', $guest);
        self::assertStringNotContainsString('cart-count', $guest);
        self::assertStringContainsString('<span class="cart-count">3</span>', $member);
    }

    /** @param array<string, mixed> $get */
    private static function renderSearchForm(array $get, bool $full): string
    {
        require_once __DIR__ . '/../../includes/search.php';
        $parsed = parse_search_criteria($get);
        $criteria = $parsed['criteria'];
        $errors = $parsed['errors'];
        $full_filters = $full;
        ob_start();
        include __DIR__ . '/../../includes/search_form.php';
        return (string) ob_get_clean();
    }

    public function testSearchFormKeepsSelectedConditions(): void
    {
        $html = self::renderSearchForm([
            'keyword' => '"><x>', 'players' => ['3-4'], 'genre' => ['MMO'], 'crossplay' => '1', 'price_max' => '5000', 'sort' => 'new',
        ], true);

        self::assertStringContainsString('name="players[]" value="3-4" checked', $html);
        self::assertStringContainsString('name="players[]" value="2">', $html);
        self::assertStringContainsString('name="genre[]" value="MMO" checked', $html);
        self::assertStringContainsString('name="crossplay" value="1" checked', $html);
        self::assertStringContainsString('name="price_max" min="0" value="5000"', $html);
        self::assertStringContainsString('<option value="new" selected>', $html);
        self::assertStringContainsString('value="&quot;&gt;&lt;x&gt;"', $html, 'キーワードはエスケープして戻す');
        self::assertStringContainsString('この条件で絞り込む', $html);
    }

    public function testSearchFormKeepsNumericLikePlayersValueChecked(): void
    {
        // '2' は PHP の配列キーで int 2 になるため、厳密比較だと選択状態が消えていた
        $html = self::renderSearchForm(['players' => ['2']], false);

        self::assertStringContainsString('name="players[]" value="2" checked', $html);
        self::assertStringContainsString('name="players[]" value="3-4">', $html);
    }

    public function testSearchFormOnTopHasNoGenrePriceOrSort(): void
    {
        $html = self::renderSearchForm([], false);

        self::assertStringNotContainsString('genre[]', $html);
        self::assertStringNotContainsString('price_min', $html);
        self::assertStringNotContainsString('name="sort"', $html);
        self::assertStringContainsString('この条件で探す', $html);
    }

    public function testSearchFormShowsPriceError(): void
    {
        self::assertStringContainsString('価格の下限は上限以下にしてください', self::renderSearchForm(['price_min' => '9', 'price_max' => '1'], true));
    }

    public function testFooterClosesDocument(): void
    {
        ob_start();
        include __DIR__ . '/../../includes/footer.php';
        $html = (string) ob_get_clean();

        self::assertStringContainsString('</body>', $html);
        self::assertStringContainsString('</html>', $html);
    }
}
