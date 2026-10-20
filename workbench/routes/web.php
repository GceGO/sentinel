<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;

$page = static fn (string $title): string => '<!doctype html><title>'.e($title).'</title><h1>'.e($title).'</h1><p><a href="/account/security">Account security</a></p><form method="POST" action="/logout"><input type="hidden" name="_token" value="'.csrf_token().'"><button>Sign out</button></form>';

Route::get('/', static fn () => redirect('/login'));
Route::get('/dashboard', static fn (): string => $page('Dashboard'))->middleware(['auth', 'sentinel.active']);
Route::get('/admin', static fn (): string => $page('Administration'))->middleware(['auth', 'sentinel.active']);
Route::get('/teacher', static fn (): string => $page('Teachers'))->middleware(['auth', 'sentinel.active']);
Route::get('/student', static fn (): string => $page('Students'))->middleware(['auth', 'sentinel.active']);
Route::get('/institution/{tenant}', static fn (string $tenant): string => $page('Institution '.$tenant))->middleware(['auth', 'sentinel.active']);
