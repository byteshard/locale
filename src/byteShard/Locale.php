<?php
/**
 * @copyright  Copyright (c) 2009 Bespin Studios GmbH
 * @license    See LICENSE file that is distributed with this source code
 */

namespace byteShard;

/**
 * Class Language
 * @package byteShard
 *
 * This class can be extended for different languages.
 * If you need multi-language capabilities extend this class
 * and name it:
 * applicationLanguage_en (default for all languages)
 * applicationLanguage_en_US
 * applicationLanguage_de (default for de_DE, de_AT ...)
 * applicationLanguage_de_DE
 *
 * Example:
 * Locale::get('project::b.project_label', 'de_DE')
 * This call will create an instance of applicationLanguage_de_DE and call method get_project_locale which needs to return an array['b']['project_label']
 * if either the class or the method or any array index is not found another try with the class applicationLanguage_de will be made
 * after an unsuccessful try a last call with the class applicationLanguage_en will be made
 * if everything fails, an unexpected error occurs
 *
 * to override byteShard framework locale create a method 'get_byteShard_locale' in the application locale classes
 *
 */
class Locale
{
    private static string   $default_locale       = 'en';
    private static string   $default_debug_result = 'No locale found';
    private static string   $default_result       = 'An unexpected error occurred';
    protected static ?array $locale               = null;

    /**
     * this is used to return the locale name if it is defined in the respective local class
     * getLocaleName will return 'english' if it is called with 'en' and $locale_name in the en class is defined as 'english'
     * @var string
     */
    protected static string $locale_name                  = '';
    protected static string $application_locale_namespace = '\\App\\Locale\\';
    protected static string $byteShard_locale_namespace   = '\\byteShard\\Internal\\Locale\\';

    /**
     * @param string $token
     * @return null|string
     */
    protected function getLocale(string $token): ?string
    {
        $baseToken = self::getBaseLocale($token);
        if ($baseToken !== null) {
            $method = 'get_'.$baseToken.'_locale';
            // baseToken is always seperated by '::', so we need to add +2 here
            $tokens = explode('.', substr($token, strlen($baseToken) + 2));
        } else {
            $tokens = explode('.', $token);
            $method = 'get_'.array_shift($tokens).'_locale';
        }

        if (method_exists($this, $method)) {
            $this->{$method}();
            $locale = self::$locale;

            while (!empty($tokens)) {
                $idx = array_shift($tokens);
                if (isset($locale[$idx])) {
                    $locale = $locale[$idx];
                } else {
                    return null;
                }
            }
            if (is_string($locale)) {
                return $locale;
            }
            return null;
        }
        return null;
    }

    protected static function factory(): Locale
    {
        $locale_class = static::class;
        return new $locale_class();
    }

    /**
     * this will return an array of all possible locales to parse
     * for example if the $locale is 'de_DE' and the default locale is 'en' this will return the following array:
     * $language_array[0]['language'] = 'de_DE';
     * $language_array[1]['language'] = 'de';
     * $language_array[2]['language'] = 'en';
     *
     * @param string $token
     * @param null|string $locale
     * @return array
     */
    static private function getLanguageArray(string $token, string $locale = null): array
    {
        if ($locale === null && class_exists('\byteShard\Session') && method_exists('\byteShard\Session', 'getLocale')) {
            $locale = Session::getLocale();
        }

        $locale = $locale ?? 'en';

        $language_array[]['language'] = self::$default_locale;
        if ($locale !== null && $locale !== self::$default_locale) {
            $tmp                   = explode('_', $locale);
            $concatenated_language = '';
            foreach ($tmp as $loc) {
                $concatenated_language = $concatenated_language === '' ? $loc : $concatenated_language.'_'.$loc;
                if ($concatenated_language !== self::$default_locale) {
                    $arr = array('language' => $concatenated_language);
                    array_unshift($language_array, $arr);
                    unset($arr);
                }
            }
        }

        if (str_starts_with($token, 'byteShard')) {
            return self::getByteShardLanguageArray($language_array, $token, array('application' => self::$application_locale_namespace, 'byteShard' => self::$byteShard_locale_namespace));
        }
        return self::getApplicationLanguageArray($language_array, $token, self::$application_locale_namespace);
    }

    /**
     * @param array $languages
     * @param string $token
     * @param array $locale_namespace
     * @return array
     */
    static private function getByteShardLanguageArray(array $languages, string $token, array $locale_namespace): array
    {
        $app_tokens = array($token);
        if (str_contains($token, '::')) {
            $token = str_replace('::', '.', $token);
        }
        $bs_tokens      = array(substr($token, 10));
        $language_array = [];
        if (defined('DEBUG_LOCALE') && DEBUG_LOCALE === true) {
            $tkn_arr = explode('.', $token);
            $method  = array_shift($tkn_arr);
            array_unshift($tkn_arr, $method, 'debug');
            array_unshift($app_tokens, implode('.', $tkn_arr));

            $tkn_arr = explode('.', $token);
            array_shift($tkn_arr);
            $method = array_shift($tkn_arr);
            array_unshift($tkn_arr, $method, 'debug');
            array_unshift($bs_tokens, implode('.', $tkn_arr));
        }

        foreach ($app_tokens as $app_token) {
            foreach ($languages as $language) {
                $language_array[] = array('language' => $language['language'], 'locale_class' => $locale_namespace['application'].$language['language'], 'tokens' => [$app_token]);
            }
        }
        foreach ($bs_tokens as $bs_token) {
            foreach ($languages as $language) {
                $language_array[] = array('language' => $language['language'], 'locale_class' => $locale_namespace['byteShard'].$language['language'], 'tokens' => [$bs_token]);
            }
        }
        return $language_array;
    }

    /**
     * @param array $languages
     * @param string $token
     * @param string $application_locale_namespace
     * @return array
     */
    static private function getApplicationLanguageArray(array $languages, string $token, string $application_locale_namespace): array
    {
        $app_tokens = array($token);
        if (defined('DEBUG_LOCALE') && DEBUG_LOCALE === true) {
            $tkn_arr = explode('.', $token);
            $method  = array_shift($tkn_arr);
            array_unshift($tkn_arr, $method, 'debug');
            array_unshift($app_tokens, implode('.', $tkn_arr));
        }
        $language_array = [];
        foreach ($languages as $language) {
            $language_array[] = array('language' => $language['language'], 'locale_class' => $application_locale_namespace.$language['language'], 'tokens' => $app_tokens);
        }
        return $language_array;
    }

    /**
     * returns an array with the indices (bool)found and the string in the locale that matches the token
     * e.g. database.update.failed
     *
     * if no string is found for the locale the search continues for the next locale
     * e.g. 'de_DE' -> 'de' (split at '_') -> 'en' (default)
     *
     * @param string $token
     * @param string|null $locale
     * @return array
     */
    static public function getArray(string $token, string $locale = null): array
    {
        $locale_array          = [];
        $locale_array['found'] = false;

        if ($token === '') {
            $locale_array['locale'] = self::appendLocale(null, $token);
            return $locale_array;
        }

        $initial_token  = $token;
        self::$locale   = null;
        $language_array = self::getLanguageArray($token, $locale);

        $locale_string       = null;
        $testedLocaleClasses = [];
        foreach ($language_array as $language) {
            $class = $language['locale_class'];
            if (class_exists($class)) {
                /* @var $class Locale */
                $tokens = $language['tokens'];
                if (is_array($tokens)) {
                    foreach ($tokens as $tkn) {
                        $locale_string = $class::factory()->getLocale($tkn);
                        if ($locale_string !== null) {
                            $locale_array['found'] = true;
                            $locale_array['token'] = $tkn;
                            break 2;
                        }
                    }
                }
            }
            $testedLocaleClasses[] = $class;
        }
        if ($locale_array['found'] === false) {
            $ignoreEndings = ['Tooltip', 'Note'];
            $ignore        = false;
            foreach ($ignoreEndings as $ending) {
                if (substr($initial_token, strlen($initial_token) - strlen($ending), strlen($initial_token)) === $ending) {
                    $ignore = true;
                    break;
                }
            }
            if (!$ignore) {
                Debug::error('No Locale class found: '.implode(', ', $testedLocaleClasses).' - Token: '.$initial_token);
            }
        }
        $locale_array['raw']    = $locale_string;
        $locale_array['locale'] = self::appendLocale($locale_string, $initial_token);
        return $locale_array;
    }

    /**
     * returns the default_result or the default_debug_result in case an empty locale is passed
     * appends the token to the locale in case DEBUG_LOCALE_TOKEN is true
     *
     * @param string|null $locale
     * @param string|null $token
     * @return string
     */
    static private function appendLocale(?string $locale, ?string $token): string
    {
        $prefix = '';
        if (defined('DEBUG_LOCALE') && DEBUG_LOCALE === true) {
            $prefix = 'DEBUG: ';
        }
        if ($locale === null) {
            if (defined('DEBUG_LOCALE') && DEBUG_LOCALE === true) {
                $locale = self::$default_debug_result;
            } else {
                $locale = self::$default_result;
            }
        }
        if (defined('DEBUG_LOCALE_TOKEN') && DEBUG_LOCALE_TOKEN === true) {
            if ($token === null || $token === '') {
                $locale = 'empty token: '.$prefix.$locale;
            } else {
                $locale = $token.': '.$prefix.$locale;
            }
        }
        return $locale;
    }

    /**
     * returns the string in the locale that matches the token
     * e.g. database.update.failed
     *
     * if no string is found for the locale the search continues for the next locale
     * e.g. 'de_DE' -> 'de' (split at '_') -> 'en' (default)
     *
     * @param string $token
     * @param string|null $locale
     * @return string
     */
    static public function get(string $token, string $locale = null): string
    {
        $array = self::getArray($token, $locale);
        return $array['locale'];
    }

    /**
     * @param string $locale
     * @return string
     */
    static public function getLocaleName(string $locale): string
    {
        $language_array        = [];
        $tmp                   = explode('_', $locale);
        $concatenated_language = '';
        foreach ($tmp as $loc) {
            $concatenated_language = $concatenated_language === '' ? $loc : $concatenated_language.'_'.$loc;
            array_unshift($language_array, $concatenated_language);
        }
        foreach ($language_array as $language) {
            $locale_class = self::$application_locale_namespace.$language;
            if (class_exists($locale_class)) {
                if (!empty($locale_class::$locale_name)) {
                    return $locale_class::$locale_name;
                }
            }
        }
        foreach ($language_array as $language) {
            $locale_class = self::$byteShard_locale_namespace.$language;
            if (class_exists($locale_class) && !empty($locale_class::$locale_name)) {
                return $locale_class::$locale_name;
            }
        }
        return $locale;
    }

    /**
     * Get the base token before the '::' of the token string if available
     * @param string $token
     * @return string|null
     */
    static public function getBaseLocale(string $token): ?string
    {
        $colon  = strpos($token, '::');
        $period = strpos($token, '.');
        if ($colon !== false && ($period === false || $colon < $period)) {
            return substr($token, 0, $colon);
        }
        return null;
    }
}
