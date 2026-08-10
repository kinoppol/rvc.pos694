<?php
declare(strict_types=1);

namespace App\Services;

use PDO;

/**
 * Session-backed cart for the active POS terminal. Kept intentionally
 * simple (server session, not DB) since a cart is transient until it is
 * either held (persisted as a `sales` row with status=held) or paid.
 */
class CartService
{
    private const KEY = 'pos_cart';

    public static function get(): array
    {
        if (!isset($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = ['items' => [], 'member_id' => null, 'coupon_code' => null];
        }
        return $_SESSION[self::KEY];
    }

    public static function addItem(int $variantId, array $meta, int $qty): void
    {
        $cart = self::get();
        if (isset($cart['items'][$variantId])) {
            $cart['items'][$variantId]['qty'] += $qty;
        } else {
            $cart['items'][$variantId] = array_merge($meta, ['qty' => $qty]);
        }
        $_SESSION[self::KEY] = $cart;
    }

    public static function updateQty(int $variantId, int $qty): void
    {
        $cart = self::get();
        if (!isset($cart['items'][$variantId])) {
            return;
        }
        if ($qty <= 0) {
            unset($cart['items'][$variantId]);
        } else {
            $cart['items'][$variantId]['qty'] = $qty;
        }
        $_SESSION[self::KEY] = $cart;
    }

    public static function setCoupon(?string $code): void
    {
        $cart = self::get();
        $cart['coupon_code'] = $code;
        $_SESSION[self::KEY] = $cart;
    }

    public static function setMember(?int $memberId): void
    {
        $cart = self::get();
        $cart['member_id'] = $memberId;
        $_SESSION[self::KEY] = $cart;
    }

    public static function clear(): void
    {
        $_SESSION[self::KEY] = ['items' => [], 'member_id' => null, 'coupon_code' => null];
    }

    /**
     * Computes subtotal / discounts (coupon + birthday-month) / VAT
     * (7% already included in item prices, so VAT is derived, not added)
     * / grand total.
     */
    public static function totals(PDO $db): array
    {
        $cart = self::get();
        $subtotal = 0.0;
        foreach ($cart['items'] as $item) {
            $subtotal += $item['price'] * $item['qty'];
        }

        $discount = 0.0;
        $discountLabel = [];

        if ($cart['coupon_code']) {
            $stmt = $db->prepare('SELECT * FROM coupons WHERE code = ? AND active = 1');
            $stmt->execute([$cart['coupon_code']]);
            $coupon = $stmt->fetch();
            if ($coupon) {
                $discount += $coupon['discount_type'] === 'percent'
                    ? $subtotal * ((float) $coupon['discount_value'] / 100)
                    : (float) $coupon['discount_value'];
                $discountLabel[] = 'คูปอง ' . $coupon['code'];
            }
        }

        $member = null;
        if ($cart['member_id']) {
            $stmt = $db->prepare('SELECT * FROM members WHERE id = ?');
            $stmt->execute([$cart['member_id']]);
            $member = $stmt->fetch() ?: null;
            if ($member && $member['birthdate'] && date('m') === date('m', strtotime($member['birthdate']))) {
                $discount += $subtotal * 0.10;
                $discountLabel[] = 'ส่วนลดวันเกิด 10%';
            }
        }

        $grandTotal = max(0, $subtotal - $discount);
        $vatTotal = round($grandTotal * 7 / 107, 2);

        return [
            'subtotal' => round($subtotal, 2),
            'discount_total' => round($discount, 2),
            'discount_label' => implode(' + ', $discountLabel),
            'vat_total' => $vatTotal,
            'grand_total' => round($grandTotal, 2),
            'member' => $member,
            'item_count' => array_sum(array_column($cart['items'], 'qty')),
        ];
    }
}
