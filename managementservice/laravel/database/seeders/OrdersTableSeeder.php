<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\Partner;
use League\Csv\Reader;
use Faker\Factory as Faker;
use Illuminate\Database\Seeder;

class OrdersTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $filePath = 'pr30.txt'; // Đường dẫn tới file trên máy tính của bạn
        $fileContent = file_get_contents($filePath); // Đọc nội dung file
        $lines = explode("\n", $fileContent); // Tách các dòng trong file

        $csvFilePath = 'output3.csv'; // Đường dẫn tới file CSV chứa thông tin địa chỉ
        $csv = Reader::createFromPath($csvFilePath, 'r');
        $csv->setHeaderOffset(0); // Bỏ qua dòng tiêu đề

        $addresses = [];
        foreach ($csv->getRecords() as $record) {
            $addresses[] = $record['address'];
        }

        // Khởi tạo Faker để tạo dữ liệu giả
        $faker = Faker::create('vi_VN');

        // Lấy tất cả các partner_id từ bảng partners
        $partnerIds = Partner::pluck('id')->all();

        // Lặp qua từng dòng trong file pr30.txt
        foreach ($lines as $index => $line) {
            if (!empty(trim($line))) { // Kiểm tra dòng không trống
                $data = explode(" ", trim($line)); // Sử dụng tab làm dấu phân cách để tách dữ liệu

                // Tạo số điện thoại và tên khách hàng giả
                $phone = $faker->phoneNumber;
                $customerName = $faker->name;

                // Lấy ngẫu nhiên partner_id từ danh sách partner_id
                $partnerId = $faker->randomElement($partnerIds);

                // Tạo mã đơn hàng bằng phương thức generateCodeOrder
                $order = new Order();
                $codeOrder = $order->generateCodeOrder($partnerId);

                // Tạo thời gian giả từ tháng 1/2024 đến tháng 5/2024
                $createdAt = $faker->dateTimeBetween('2024-01-01', '2024-05-31');

                // Lấy địa chỉ tương ứng với chỉ mục hiện tại
                $address = isset($addresses[$index]) ? $addresses[$index] : null;

                // Tạo đối tượng Order mới
                Order::create([
                    'longitude' => $data[2],
                    'latitude' => $data[1],
                    'time_service' => $data[3],
                    'mass_of_order' => $data[4],
                    'phone' => $phone,
                    'customer_name' => $customerName,
                    'partner_id' => $partnerId,
                    'code_order' => $codeOrder,
                    'address' => $address, // Thêm trường address
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }
    }
}
