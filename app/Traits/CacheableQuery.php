<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Model;

/**
 * CacheableQuery Trait
 * 
 * Provides reusable cache key generation and tagging logic for query results.
 * Helps maintain consistent cache key format: laporin:entity:action:params_hash
 * 
 * Usage in Model:
 * - Use CacheableQuery::cacheKey('list', 'all') to generate cache key
 * - Use CacheableQuery::cacheTag() to get cache tag for invalidation
 * - Use CacheableQuery::cachePrefix() to get cache prefix for pattern matching
 * 
 * Example:
 *   $key = Report::cacheKey('list', 'all');              // laporin:report:list:all
 *   $tag = Report::cacheTag();                           // report
 *   CacheHelper::remember($key, 3600, fn() => Report::all());
 */
trait CacheableQuery
{
    /**
     * Generate cache key for query results
     * Format: laporin:entity:action[:params_hash]
     * 
     * @param string $action The action being cached (list, detail, search, etc)
     * @param mixed ...$params Additional parameters to include in cache key
     * @return string Cache key
     * 
     * @example
     * CacheableQuery::cacheKey('list', 'all')
     * // Returns: 'laporin:report:list:all'
     * 
     * CacheableQuery::cacheKey('detail', 123)
     * // Returns: 'laporin:report:detail:202cb962ac59075b964b07152d234b70' (md5 of 123)
     */
    public static function cacheKey(string $action, ...$params): string
    {
        // Get the entity name from the model class
        $entityName = strtolower(class_basename(static::class));
        
        // Build parameter hash
        if (empty($params)) {
            $paramStr = '';
        } else {
            $hashedParams = array_map(function ($param) {
                if (is_scalar($param)) {
                    return md5((string)$param);
                } else {
                    return md5(json_encode($param));
                }
            }, $params);
            $paramStr = ':' . implode(':', $hashedParams);
        }
        
        return "laporin:{$entityName}:{$action}{$paramStr}";
    }

    /**
     * Generate cache tag for grouped invalidation
     * Returns lowercase model class name for use with Cache::tags()
     * 
     * @return string Cache tag
     * 
     * @example
     * CacheableQuery::cacheTag()
     // Returns: 'report' (for Report model)
//
// Cache::tags('report')->flush()  // Invalidates all 'reports' tagged cache
     */
    public static function cacheTag(): string
    {
        return strtolower(class_basename(static::class));
    }

    /**
     * Generate cache prefix for pattern matching
     * Used for wildcard cache invalidation (e.g., 'laporin:report:*')
     * 
     * @param string $action Optional action filter (list, detail, etc)
     * @return string Cache prefix for pattern matching
     * 
     * @example
     * CacheableQuery::cachePrefix()
     * // Returns: 'laporin:report:'
     * 
     * CacheableQuery::cachePrefix('list')
     * // Returns: 'laporin:report:list:'
     */
    public static function cachePrefix(?string $action = null): string
    {
        $entityName = strtolower(class_basename(static::class));
        
        if ($action) {
            return "laporin:{$entityName}:{$action}:";
        }
        
        return "laporin:{$entityName}:";
    }
}
