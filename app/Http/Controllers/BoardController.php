<?php

namespace App\Http\Controllers;

use App\Models\Board;
use Illuminate\Http\Request;
use Illuminate\View\View;

// A company's job board, on the company's own domain.
class BoardController extends Controller
{
    public function index(Request $request): View
    {
        $board = Board::current($request);

        return view('board.index', [
            'board' => $board,
            'jobs' => $board->jobPostings()->orderBy('id')->get(),
        ]);
    }

    public function show(Request $request, int $job): View
    {
        $board = Board::current($request);

        return view('board.show', [
            'board' => $board,
            // Through the board, so one company's job IDs don't work on another's domain.
            'job' => $board->jobPostings()->findOrFail($job),
        ]);
    }
}
