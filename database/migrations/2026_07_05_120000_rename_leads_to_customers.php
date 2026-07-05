<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Chuyển hệ thống từ mô hình "theo trường" (leads) sang "theo khách hàng"
 * (customers). Đổi tên bảng + cột, bảo toàn toàn bộ dữ liệu hiện có.
 *
 *  - Bảng  leads        -> customers
 *  - Cột   school_name  -> name
 *  - Bỏ    school_level, student_size (đặc thù trường học)
 *  - Thêm  phone, email (thông tin liên hệ khách hàng)
 *  - FK    lead_id      -> customer_id  ở các bảng con
 */
return new class extends Migration
{
    /** @var array<int, string> Các bảng con tham chiếu tới lead/customer. */
    private array $childTables = ['contacts', 'deals', 'tasks', 'events', 'activities'];

    public function up(): void
    {
        // Đổi tên bảng cha trước — InnoDB tự cập nhật FK ở bảng con trỏ sang tên mới.
        Schema::rename('leads', 'customers');

        Schema::table('customers', function (Blueprint $table) {
            $table->renameColumn('school_name', 'name');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['school_level', 'student_size']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('phone', 30)->nullable()->after('name');
            $table->string('email')->nullable()->after('phone');
        });

        // Đổi tên cột FK ở các bảng con (MySQL 8 giữ nguyên FK + index).
        foreach ($this->childTables as $child) {
            Schema::table($child, function (Blueprint $table) {
                $table->renameColumn('lead_id', 'customer_id');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->childTables as $child) {
            Schema::table($child, function (Blueprint $table) {
                $table->renameColumn('customer_id', 'lead_id');
            });
        }

        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['phone', 'email']);
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->renameColumn('name', 'school_name');
        });

        Schema::table('customers', function (Blueprint $table) {
            $table->string('school_level')->default('khac');
            $table->unsignedInteger('student_size')->default(0);
        });

        Schema::rename('customers', 'leads');
    }
};
