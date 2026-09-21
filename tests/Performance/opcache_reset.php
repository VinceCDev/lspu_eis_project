<?php
// Scratch-stack helper: the dev XAMPP Apache and this private Apache share one opcache (same user, same DLL), and the perf
// php.ini runs with validate_timestamps=0 like production - so after editing code, reset the shared cache once.
echo opcache_reset() ? "opcache reset\n" : "opcache_reset failed\n";
