<?php

return [
    'statesDir' => call_user_func(function () {
        if (function_exists('posix_getuid')) {
            $uid = posix_getuid();

            if ($uid === 0) {
                return '/var/lib/logmon';
            }

            $home = (string) getenv('HOME');

            if ($home === '') {
                $userInfo = posix_getpwuid($uid);
                $home = isset($userInfo['dir']) ? $userInfo['dir'] : '';
            }

            if ($home !== '') {
                return rtrim($home, '/') . '/.local/share/logmon';
            }
        }

        return getcwd();
    }),
    'maxLines' => 100,
];
