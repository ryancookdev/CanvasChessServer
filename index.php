<?php
    // This script can be configured for any engine that
    // supports the Chess Engine Communication Protocol
    define('ENGINE_PATH', '/usr/games/crafty');

    // Set any necessary environment variables
    //putenv('CRAFTY_BOOK_PATH=/path/to/crafty/book');
    //putenv('CRAFTY_TB_PATH=/path/to/crafty/tb');

    // Read the POST data from the client
    $rest_json = file_get_contents('php://input');
    $request = json_decode($rest_json, true);

    // Send the response
    $response = handleRequest($request);
    echo json_encode($response);

    function handleRequest ($request) {

        // Prepare the response to be sent to the client
        $response = ['move' => '', 'error' => ''];

        if (isset($request['fen'])) {

            // If no move is provided, tell the engine to go first
            $move = empty(trim($request['move'])) ? 'go' : $request['move'];

            try {

                // Ask the engine to play a move
                $response['move'] = getMove($request['fen'], $move);

            } catch (Exception $e) {

                // The engine found a problem
                $response['error'] = $e->getMessage();
                http_response_code(400); // Bad request

            }

        } else {

            $response['error'] = 'Request did not include FEN.';

        }

        return $response;
    
    }

    function getMove($fen, $move) {

        $engine_move = '';

        // Validate that the move is formatted properly (eg. e2e4)
        if ($move !== 'go' && !preg_match("/^([a-z][1-8]){2}$/", $move)) {

            throw new Exception('Invalid move format.');

        }

        // Validate that the FEN is formatted properly
        // See: http://en.wikipedia.org/wiki/Forsyth%E2%80%93Edwards_Notation#Definition
        if (!preg_match("/^([1-8rnbqkp]{1,8}\/){7}[1-8rnbqkp]{1,8} [wb] [-kq]{1,5} (-|([a-h][1-8])) \d+ \d+$/i", $fen)) {

            throw new Exception('Invalid FEN format.');

        }

        $desc = array (
            0 => array ('pipe', 'r'),
            1 => array ('pipe', 'w'),
            2 => array ('file', './error.log', 'a')
        );

        $cwd = './';
        $process = proc_open(ENGINE_PATH, $desc, $pipes, $cwd);
        $env = null;

        if (is_resource($process)) {

            fwrite($pipes[0], 'log off' . PHP_EOL); // Do not generate log files
            fwrite($pipes[0], 'xboard' . PHP_EOL); // Set protocol to CECP
            fwrite($pipes[0], 'st 0.1' . PHP_EOL); // Limit thinking to 0.1 seconds
            fwrite($pipes[0], 'no post' . PHP_EOL); // Turn off pondering/analysis
            fwrite($pipes[0], 'setboard ' . $fen . PHP_EOL); // Set the position
            fwrite($pipes[0], $move . PHP_EOL); // Play the move (or say 'go')
            fwrite($pipes[0], 'quit' . PHP_EOL); // Shut down the engine
            fclose($pipes[0]); // Clean up
            
            // Read from standard output
            $output = stream_get_contents($pipes[1]);
            
            // Extract the engine's move
            $start = strrpos($output, 'move ') + 5;
            $end = strpos($output, "\n", $start);
            $engine_move = substr($output, $start, $end - $start);
            
        } else {

            //http_response_code(500); // Internal server error
            throw new Exception('Cannot start engine.');

        }
        
        return $engine_move;
            
    }
