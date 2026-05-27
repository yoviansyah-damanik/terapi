@extends('errors::layout')
@section('code', '503')
@section('title', 'Layanan Tidak Tersedia')
@section('message', $exception->getMessage() ?: 'Sistem sedang dalam pemeliharaan. Silakan coba beberapa saat lagi.')
