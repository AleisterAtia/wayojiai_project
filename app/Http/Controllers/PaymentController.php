<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon; // Untuk keperluan tanggal

// ⬇️ TAMBAHAN: Impor Event Broadcast Anda
use App\Events\PaymentConfirmed; 
use App\Events\OrderStatusUpdated; 

class PaymentController extends Controller
{
    /**
     * Menampilkan struk pembayaran.
     * Menggunakan Route Model Binding untuk Order, atau mencari Order berdasarkan ID.
     */
    public function update(Request $request, $id)
    {
        // 1. Cari Order
        $order = Order::findOrFail($id);
        
        // ⬇️ VARIABEL PENTING: SIMPAN STATUS LAMA SEBELUM DIUBAH
        $oldStatus = $order->status;

        // 2. Tentukan Status Baru
        $newStatus = 'process'; 
        
        // Update Data Order
        $order->status = $newStatus; 
        $order->payment_method = $request->payment_method;
        $order->payment_status = 'paid'; 
        $order->save();

        // =========================================================
        // SISTEM POIN MEMBER (DIPERBAIKI)
        // =========================================================

        // 🛑 PERBAIKAN UTAMA DI SINI 🛑
        // Kita hanya menambahkan poin JIKA status sebelumnya adalah 'new'.
        // Jika status sebelumnya sudah 'process', 'done', atau lainnya, JANGAN tambah poin lagi.
        // =========================================================
        // LOGIKA BROADCAST
        // =========================================================
        
        // Broadcast PaymentConfirmed hanya jika transisi dari New -> Process
        if ($oldStatus === 'new' && $newStatus === 'process') {
            broadcast(new PaymentConfirmed($order));
        } else {
            broadcast(new OrderStatusUpdated($order)); 
        }

        return response()->json([
            'success' => true,
            'message' => 'Pembayaran berhasil dikonfirmasi',
            'order_id' => $order->id
        ]);
    }

    /**
     * Menampilkan struk pembayaran.
     */
    public function showReceipt($orderId)
    {
        // Konten showReceipt tidak diubah, untuk menjaga fungsionalitas order manual.
        
        // 1. Ambil Order dan detailnya dengan Eager Loading
        $order = Order::with(['orderItems.menu'])->findOrFail($orderId);

        // 2. Mapping data
        $subtotal = $order->subtotal ?? 0;
        $totalAkhir = $order->total_price ?? $subtotal;
        $uangDiterima = $order->uang_diterima ?? $totalAkhir;
        $kembalian = $order->kembalian ?? 0;
        $paymentMethod = $order->payment_method ?? 'Tunai';

        $data = [
            'order' => $order,
            'orderId' => $order->order_code ?? $order->id, // Fallback ke ID jika order_code null
            'customerName' => $order->customer_name,
            'items' => $order->orderItems,

            // Variabel Harga & Diskon
            'subtotal' => (float) $subtotal,
            'discountAmount' => $order->discount_amount ?? 0,
            'discountPercentage' => $order->discount_percentage ?? 0,
            'totalAkhir' => (float) $totalAkhir,

            // Detail Bayar
            'paymentMethod' => $paymentMethod,
            'uangDiterima' => (float) $uangDiterima,
            'kembalian' => (float) $kembalian,
        ];

        return view('kasir.partials.cetak_struk', $data);
    }
}