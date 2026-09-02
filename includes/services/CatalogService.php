<?php
declare(strict_types=1);

final class CatalogService
{
    public static function catalog(): array
    {
        $pdo = Database::connection();
        $categories = $pdo->query(
            'SELECT id, name, icon, sort_order FROM categories WHERE active = 1 AND deleted_at IS NULL ORDER BY sort_order, name'
        )->fetchAll();
        $products = $pdo->query(
            'SELECT id, category_id, name, description, price, image_path, featured, sort_order
             FROM products WHERE active = 1 AND available = 1 AND deleted_at IS NULL ORDER BY sort_order, name'
        )->fetchAll();
        $groups = $pdo->query(
            "SELECT pmg.product_id, mg.id, mg.name, mg.min_choices, mg.max_choices, mg.required, mg.sort_order
             FROM product_modifier_groups pmg
             INNER JOIN modifier_groups mg ON mg.id = pmg.modifier_group_id
             WHERE mg.active = 1 AND mg.deleted_at IS NULL
             ORDER BY pmg.product_id, pmg.sort_order, mg.sort_order"
        )->fetchAll();
        $modifiers = $pdo->query(
            'SELECT id, modifier_group_id, name, price_delta, sort_order
             FROM modifiers WHERE active = 1 AND deleted_at IS NULL ORDER BY sort_order, name'
        )->fetchAll();

        $optionsByGroup = [];
        foreach ($modifiers as $modifier) {
            $modifier['id'] = (int) $modifier['id'];
            $modifier['modifier_group_id'] = (int) $modifier['modifier_group_id'];
            $optionsByGroup[$modifier['modifier_group_id']][] = $modifier;
        }
        $groupsByProduct = [];
        foreach ($groups as $group) {
            $group['id'] = (int) $group['id'];
            $group['product_id'] = (int) $group['product_id'];
            $group['min_choices'] = (int) $group['min_choices'];
            $group['max_choices'] = (int) $group['max_choices'];
            $group['required'] = (bool) $group['required'];
            $group['options'] = $optionsByGroup[$group['id']] ?? [];
            $groupsByProduct[$group['product_id']][] = $group;
        }
        $productsByCategory = [];
        foreach ($products as $product) {
            $product['id'] = (int) $product['id'];
            $product['category_id'] = (int) $product['category_id'];
            $product['featured'] = (bool) $product['featured'];
            $product['modifier_groups'] = $groupsByProduct[$product['id']] ?? [];
            $productsByCategory[$product['category_id']][] = $product;
        }
        foreach ($categories as &$category) {
            $category['id'] = (int) $category['id'];
            $category['products'] = $productsByCategory[$category['id']] ?? [];
        }
        unset($category);
        return ['categories' => $categories, 'updated_at' => date(DATE_ATOM)];
    }
}
