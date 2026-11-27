<?php
/**
 * SiteSetting Model
 * Manages global application settings in site_settings table
 */

require_once __DIR__ . '/BaseModel.php';

class SiteSetting extends BaseModel {
    protected $table = 'site_settings';
    protected static $cache = null;

    protected static $aliases = [
        'fevicon_logo' => ['favicon_icon', 'favicon_logo'],
        'favicon_logo' => ['favicon_icon', 'fevicon_logo'],
        'favicon_icon' => ['fevicon_logo', 'favicon_logo'],
        'facebook_url' => ['social_facebook'],
        'social_facebook' => ['facebook_url'],
        'twitter_url' => ['social_twitter'],
        'social_twitter' => ['twitter_url'],
        'instagram_url' => ['social_instagram'],
        'social_instagram' => ['instagram_url'],
        'linkedin_url' => ['social_linkedin'],
        'social_linkedin' => ['linkedin_url'],
        'youtube_link' => ['social_youtube', 'youtube_url'],
        'youtube_url' => ['social_youtube', 'youtube_link'],
        'social_youtube' => ['youtube_link', 'youtube_url'],
        'whatsapp_url' => ['social_whatsapp', 'contact_whatsapp'],
        'social_whatsapp' => ['whatsapp_url'],
    ];

    /**
     * Get a setting value by key
     */
    public static function get($key, $default = null) {
        if (self::$cache === null) {
            self::loadCache();
        }
        if (isset(self::$cache[$key]) && self::$cache[$key] !== '') {
            return self::$cache[$key];
        }
        if (isset(self::$aliases[$key])) {
            foreach (self::$aliases[$key] as $altKey) {
                if (isset(self::$cache[$altKey]) && self::$cache[$altKey] !== '') {
                    return self::$cache[$altKey];
                }
            }
        }
        return self::$cache[$key] ?? $default;
    }

    /**
     * Set a single setting
     */
    public static function set($key, $value, $group = 'general') {
        try {
            $db = (new Database())->getConnection();
            if (!$db) return false;
            $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value, setting_group) 
                VALUES (:key, :val, :grp) 
                ON DUPLICATE KEY UPDATE setting_value = :val2, setting_group = :grp2");
            $res = $stmt->execute([
                ':key' => $key,
                ':val' => $value,
                ':grp' => $group,
                ':val2' => $value,
                ':grp2' => $group
            ]);
            if (self::$cache !== null) {
                self::$cache[$key] = $value;
            }
            return $res;
        } catch (Exception $e) {
            error_log("SiteSetting::set error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Set multiple settings at once
     */
    public static function setMultiple(array $settings, $group = 'general') {
        $db = (new Database())->getConnection();
        if (!$db) return false;
        $db->beginTransaction();
        try {
            $stmt = $db->prepare("INSERT INTO site_settings (setting_key, setting_value, setting_group) 
                VALUES (:key, :val, :grp) 
                ON DUPLICATE KEY UPDATE setting_value = :val2, setting_group = :grp2");
            foreach ($settings as $key => $val) {
                $stmt->execute([
                    ':key' => $key,
                    ':val' => $val,
                    ':grp' => $group,
                    ':val2' => $val,
                    ':grp2' => $group
                ]);
                if (self::$cache !== null) {
                    self::$cache[$key] = $val;
                }
            }
            $db->commit();
            return true;
        } catch (Exception $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            error_log("SiteSetting::setMultiple error: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Get all settings as key => value associative array
     */
    public static function getAll() {
        if (self::$cache === null) {
            self::loadCache();
        }
        return self::$cache ?? [];
    }

    /**
     * Get all settings by group
     */
    public static function getByGroup($group) {
        try {
            $db = (new Database())->getConnection();
            if (!$db) return [];
            $stmt = $db->prepare("SELECT setting_key, setting_value FROM site_settings WHERE setting_group = :grp");
            $stmt->execute([':grp' => $group]);
            return $stmt->fetchAll(PDO::FETCH_KEY_PAIR) ?: [];
        } catch (Exception $e) {
            return [];
        }
    }

    /**
     * Upload and update a logo/icon file
     */
    public static function uploadFile($fileData, $settingKey, $group = 'seo_identity') {
        if (empty($fileData['tmp_name']) || !is_uploaded_file($fileData['tmp_name'])) {
            return false;
        }

        $allowedExts = ['png', 'jpg', 'jpeg', 'svg', 'webp', 'ico'];
        $origName = $fileData['name'] ?? '';
        $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedExts)) {
            return false;
        }

        $uploadDir = __DIR__ . '/../uploads/settings/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Clean filename
        $safeName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', pathinfo($origName, PATHINFO_FILENAME));
        $newFilename = time() . '_' . $settingKey . '_' . $safeName . '.' . $ext;
        $destPath = $uploadDir . $newFilename;

        if (move_uploaded_file($fileData['tmp_name'], $destPath)) {
            $relativePath = 'uploads/settings/' . $newFilename;
            
            // Delete old file if it was also in uploads/settings/
            $oldPath = self::get($settingKey);
            if (!empty($oldPath) && strpos($oldPath, 'uploads/settings/') === 0) {
                $absOld = __DIR__ . '/../' . $oldPath;
                if (file_exists($absOld) && is_file($absOld)) {
                    @unlink($absOld);
                }
            }

            self::set($settingKey, $relativePath, $group);
            if (in_array($settingKey, ['favicon_icon', 'fevicon_logo', 'favicon_logo'])) {
                self::set('fevicon_logo', $relativePath, $group);
                self::set('favicon_logo', $relativePath, $group);
                self::set('favicon_icon', $relativePath, $group);
            }
            return $relativePath;
        }

        return false;
    }

    /**
     * Load settings cache from DB
     */
    protected static function loadCache() {
        try {
            $db = (new Database())->getConnection();
            self::$cache = [];
            if ($db) {
                $stmt = $db->query("SELECT setting_key, setting_value FROM site_settings");
                if ($stmt) {
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        self::$cache[$row['setting_key']] = $row['setting_value'];
                    }
                }
            }
        } catch (Exception $e) {
            self::$cache = [];
        }
    }
}
