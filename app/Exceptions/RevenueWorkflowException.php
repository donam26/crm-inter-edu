<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Ném khi vi phạm state-machine của Deal/Invoice/Payment.
 *
 * Ví dụ: issue invoice từ stage không hợp lệ, void invoice đã paid,
 * record payment vượt quá balance, xoá product đang dùng...
 */
class RevenueWorkflowException extends RuntimeException {}
