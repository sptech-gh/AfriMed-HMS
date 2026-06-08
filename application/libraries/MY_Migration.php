<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * MY_Migration Class
 * 
 * Extends the CI_Migration class to fix PHP 8 compatibility issues with 
 * static method checks on non-static migration methods (up / down).
 */
class MY_Migration extends CI_Migration
{
    public function __construct($config = array())
    {
        parent::__construct($config);
    }

    /**
     * Overrides version() to use method_exists() instead of is_callable()
     * for class method checks, resolving PHP 8 static-callable compatibility errors.
     */
    public function version($target_version)
    {
        // Note: We use strings, so that timestamp versions work on 32-bit systems
        $current_version = $this->_get_version();

        if ($this->_migration_type === 'sequential')
        {
            $target_version = sprintf('%03d', $target_version);
        }
        else
        {
            $target_version = (string) $target_version;
        }

        $migrations = $this->find_migrations();

        if ($target_version > 0 && ! isset($migrations[$target_version]))
        {
            $this->_error_string = sprintf($this->lang->line('migration_not_found'), $target_version);
            return FALSE;
        }

        if ($target_version > $current_version)
        {
            $method = 'up';
        }
        elseif ($target_version < $current_version)
        {
            $method = 'down';
            // We need this so that migrations are applied in reverse order
            krsort($migrations);
        }
        else
        {
            // Well, there's nothing to migrate then ...
            return TRUE;
        }

        // Validate all available migrations within our target range.
        $pending = array();
        foreach ($migrations as $number => $file)
        {
            // Ignore versions out of our range.
            if ($method === 'up')
            {
                if ($number <= $current_version)
                {
                    continue;
                }
                elseif ($number > $target_version)
                {
                    break;
                }
            }
            else
            {
                if ($number > $current_version)
                {
                    continue;
                }
                elseif ($number <= $target_version)
                {
                    break;
                }
            }

            // Check for sequence gaps
            if ($this->_migration_type === 'sequential')
            {
                if (isset($previous) && abs($number - $previous) > 1)
                {
                    $this->_error_string = sprintf($this->lang->line('migration_sequence_gap'), $number);
                    return FALSE;
                }

                $previous = $number;
            }

            include_once($file);
            $class = 'Migration_'.ucfirst(strtolower($this->_get_migration_name(basename($file, '.php'))));

            // Validate the migration file structure
            if ( ! class_exists($class, FALSE))
            {
                $this->_error_string = sprintf($this->lang->line('migration_class_doesnt_exist'), $class);
                return FALSE;
            }
            elseif ( ! method_exists($class, $method)) // PHP 8 compatibility fix: check method_exists instead of is_callable on string classname
            {
                $this->_error_string = sprintf($this->lang->line('migration_missing_'.$method.'_method'), $class);
                return FALSE;
            }

            $pending[$number] = array($class, $method);
        }

        // Now just run the necessary migrations
        foreach ($pending as $number => $migration)
        {
            log_message('debug', 'Migrating '.$method.' from version '.$current_version.' to version '.$number);

            $migration[0] = new $migration[0];
            call_user_func($migration);
            $current_version = $number;
            $this->_update_version($current_version);
        }

        // This is necessary when moving down, since the last migration applied
        // will be the down() method for the next migration up from the target
        if ($current_version <> $target_version)
        {
            $current_version = $target_version;
            $this->_update_version($current_version);
        }

        log_message('debug', 'Finished migrating to '.$current_version);
        return $current_version;
    }
}
