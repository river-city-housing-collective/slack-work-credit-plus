<?php
setcookie('sl_hash', false, time() - 3600, '/');
header("Location: /members-only/");
