<?php

namespace App\Services\Tournament;

use RuntimeException;

/** Something the admin tried that the tournament can't do, with a message to show them */
class TournamentException extends RuntimeException {}
