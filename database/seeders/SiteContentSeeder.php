<?php

namespace Database\Seeders;

use App\Models\SiteContent;
use Illuminate\Database\Seeder;

class SiteContentSeeder extends Seeder
{
    public function run(): void
    {
        $contents = [
            // ===== HERO SECTION =====
            ['section' => 'hero', 'content_key' => 'eyebrow', 'lang' => 'id', 'content_value' => 'PT. Karya Kembar Bersama', 'content_type' => 'text'],
            ['section' => 'hero', 'content_key' => 'eyebrow', 'lang' => 'en', 'content_value' => 'PT. Karya Kembar Bersama', 'content_type' => 'text'],
            ['section' => 'hero', 'content_key' => 'headline1', 'lang' => 'id', 'content_value' => 'Memimpin Energi', 'content_type' => 'text'],
            ['section' => 'hero', 'content_key' => 'headline1', 'lang' => 'en', 'content_value' => "Leading Indonesia's", 'content_type' => 'text'],
            ['section' => 'hero', 'content_key' => 'headline2', 'lang' => 'id', 'content_value' => 'Indonesia', 'content_type' => 'text'],
            ['section' => 'hero', 'content_key' => 'headline2', 'lang' => 'en', 'content_value' => 'Energy', 'content_type' => 'text'],
            ['section' => 'hero', 'content_key' => 'headline3', 'lang' => 'id', 'content_value' => 'Menuju Masa Depan', 'content_type' => 'text'],
            ['section' => 'hero', 'content_key' => 'headline3', 'lang' => 'en', 'content_value' => 'Into the Future', 'content_type' => 'text'],
            ['section' => 'hero', 'content_key' => 'sub', 'lang' => 'id', 'content_value' => 'Perusahaan pertambangan terpercaya yang beroperasi dengan standar internasional, berkomitmen terhadap keberlanjutan lingkungan dan kesejahteraan masyarakat Kalimantan.', 'content_type' => 'text'],
            ['section' => 'hero', 'content_key' => 'sub', 'lang' => 'en', 'content_value' => 'A trusted mining company operating to international standards, committed to environmental sustainability and the welfare of Kalimantan communities.', 'content_type' => 'text'],
            ['section' => 'hero', 'content_key' => 'cta1', 'lang' => 'id', 'content_value' => 'Pelajari Lebih Lanjut', 'content_type' => 'text'],
            ['section' => 'hero', 'content_key' => 'cta1', 'lang' => 'en', 'content_value' => 'Learn More', 'content_type' => 'text'],
            ['section' => 'hero', 'content_key' => 'cta2', 'lang' => 'id', 'content_value' => 'Hubungi Kami', 'content_type' => 'text'],
            ['section' => 'hero', 'content_key' => 'cta2', 'lang' => 'en', 'content_value' => 'Contact Us', 'content_type' => 'text'],
            ['section' => 'hero', 'content_key' => 'stats', 'lang' => 'id', 'content_value' => json_encode([
                ['value' => '20+', 'label' => 'Tahun Pengalaman'],
                ['value' => '500+', 'label' => 'Karyawan'],
                ['value' => '15M+', 'label' => 'Ton Produksi'],
                ['value' => '100%', 'label' => 'Komitmen K3'],
            ]), 'content_type' => 'json'],
            ['section' => 'hero', 'content_key' => 'stats', 'lang' => 'en', 'content_value' => json_encode([
                ['value' => '20+', 'label' => 'Years Experience'],
                ['value' => '500+', 'label' => 'Employees'],
                ['value' => '15M+', 'label' => 'Tons Produced'],
                ['value' => '100%', 'label' => 'Safety Committed'],
            ]), 'content_type' => 'json'],

            // ===== ABOUT SECTION =====
            ['section' => 'about', 'content_key' => 'eyebrow', 'lang' => 'id', 'content_value' => 'Tentang Kami', 'content_type' => 'text'],
            ['section' => 'about', 'content_key' => 'eyebrow', 'lang' => 'en', 'content_value' => 'About Us', 'content_type' => 'text'],
            ['section' => 'about', 'content_key' => 'title', 'lang' => 'id', 'content_value' => 'Siapa', 'content_type' => 'text'],
            ['section' => 'about', 'content_key' => 'title', 'lang' => 'en', 'content_value' => 'Who', 'content_type' => 'text'],
            ['section' => 'about', 'content_key' => 'titleHighlight', 'lang' => 'id', 'content_value' => 'Kami', 'content_type' => 'text'],
            ['section' => 'about', 'content_key' => 'titleHighlight', 'lang' => 'en', 'content_value' => 'We Are', 'content_type' => 'text'],
            ['section' => 'about', 'content_key' => 'desc', 'lang' => 'id', 'content_value' => 'PT. Karya Kembar Bersama adalah perusahaan pertambangan batu bara terkemuka yang beroperasi di Kalimantan Timur, Indonesia. Didirikan dengan visi untuk menjadi mitra energi terpercaya bagi Indonesia.', 'content_type' => 'text'],
            ['section' => 'about', 'content_key' => 'desc', 'lang' => 'en', 'content_value' => 'PT. Karya Kembar Bersama is a leading coal mining company operating in East Kalimantan, Indonesia. Founded with a vision to become a trusted energy partner for Indonesia.', 'content_type' => 'text'],
            ['section' => 'about', 'content_key' => 'body', 'lang' => 'id', 'content_value' => 'Dengan lebih dari dua dekade pengalaman di industri pertambangan, kami telah membangun reputasi yang kuat atas komitmen kami terhadap keselamatan kerja, kelestarian lingkungan, dan pemberdayaan masyarakat lokal.', 'content_type' => 'text'],
            ['section' => 'about', 'content_key' => 'body', 'lang' => 'en', 'content_value' => 'With over two decades of experience in the mining industry, we have built a strong reputation for our commitment to workplace safety, environmental sustainability, and local community empowerment.', 'content_type' => 'text'],
            ['section' => 'about', 'content_key' => 'highlights', 'lang' => 'id', 'content_value' => json_encode([
                'Beroperasi di Kabupaten Paser, Kalimantan Timur',
                'Standar K3 internasional (ISO 45001)',
                'Program rehabilitasi lahan pascatambang aktif',
                'Investasi berkelanjutan pada komunitas lokal',
            ]), 'content_type' => 'json'],
            ['section' => 'about', 'content_key' => 'highlights', 'lang' => 'en', 'content_value' => json_encode([
                'Operating in Paser Regency, East Kalimantan',
                'International K3 standards (ISO 45001)',
                'Active post-mining land rehabilitation program',
                'Sustainable investment in local communities',
            ]), 'content_type' => 'json'],
            ['section' => 'about', 'content_key' => 'visiText', 'lang' => 'id', 'content_value' => 'Menjadi perusahaan pertambangan energi terpercaya dan berkelanjutan di Indonesia.', 'content_type' => 'text'],
            ['section' => 'about', 'content_key' => 'visiText', 'lang' => 'en', 'content_value' => 'To be a trusted and sustainable energy mining company in Indonesia.', 'content_type' => 'text'],
            ['section' => 'about', 'content_key' => 'misiText', 'lang' => 'id', 'content_value' => 'Mengoperasikan pertambangan yang aman, efisien, dan ramah lingkungan demi kesejahteraan bangsa.', 'content_type' => 'text'],
            ['section' => 'about', 'content_key' => 'misiText', 'lang' => 'en', 'content_value' => 'To operate safe, efficient, and environmentally friendly mines for the nation\'s welfare.', 'content_type' => 'text'],
            ['section' => 'about', 'content_key' => 'badgeValue', 'lang' => 'id', 'content_value' => '20+', 'content_type' => 'text'],
            ['section' => 'about', 'content_key' => 'badgeValue', 'lang' => 'en', 'content_value' => '20+', 'content_type' => 'text'],
            ['section' => 'about', 'content_key' => 'badgeLabel', 'lang' => 'id', 'content_value' => 'Tahun Beroperasi', 'content_type' => 'text'],
            ['section' => 'about', 'content_key' => 'badgeLabel', 'lang' => 'en', 'content_value' => 'Years of Operation', 'content_type' => 'text'],

            // ===== BUSINESS SECTION =====
            ['section' => 'business', 'content_key' => 'eyebrow', 'lang' => 'id', 'content_value' => 'Bisnis Kami', 'content_type' => 'text'],
            ['section' => 'business', 'content_key' => 'eyebrow', 'lang' => 'en', 'content_value' => 'Our Business', 'content_type' => 'text'],
            ['section' => 'business', 'content_key' => 'title', 'lang' => 'id', 'content_value' => 'Lini Bisnis', 'content_type' => 'text'],
            ['section' => 'business', 'content_key' => 'title', 'lang' => 'en', 'content_value' => 'Core', 'content_type' => 'text'],
            ['section' => 'business', 'content_key' => 'titleHighlight', 'lang' => 'id', 'content_value' => 'Utama', 'content_type' => 'text'],
            ['section' => 'business', 'content_key' => 'titleHighlight', 'lang' => 'en', 'content_value' => 'Business Lines', 'content_type' => 'text'],
            ['section' => 'business', 'content_key' => 'desc', 'lang' => 'id', 'content_value' => 'Kami beroperasi di sepanjang rantai nilai pertambangan, dari eksplorasi hingga penjualan, dengan standar tertinggi di setiap tahapan.', 'content_type' => 'text'],
            ['section' => 'business', 'content_key' => 'desc', 'lang' => 'en', 'content_value' => 'We operate across the mining value chain, from exploration to sales, with the highest standards at every stage.', 'content_type' => 'text'],
            ['section' => 'business', 'content_key' => 'cards', 'lang' => 'id', 'content_value' => json_encode([
                ['icon' => 'Pickaxe', 'title' => 'Eksplorasi', 'desc' => 'Identifikasi dan pemetaan cadangan batu bara dengan teknologi geologi terkini untuk memastikan sumber daya yang optimal.'],
                ['icon' => 'Factory', 'title' => 'Produksi', 'desc' => 'Operasi penambangan terbuka (open-pit) yang efisien menggunakan alat berat modern dengan memperhatikan aspek keselamatan.'],
                ['icon' => 'TrendingUp', 'title' => 'Penjualan & Distribusi', 'desc' => 'Jaringan distribusi domestik dan ekspor yang kuat, melayani kebutuhan energi industri dan pembangkit listrik.'],
                ['icon' => 'Leaf', 'title' => 'Reklamasi Lingkungan', 'desc' => 'Program rehabilitasi dan reklamasi lahan pasca tambang yang aktif, mengembalikan fungsi ekosistem secara optimal.'],
                ['icon' => 'Zap', 'title' => 'Efisiensi Energi', 'desc' => 'Inovasi dalam penggunaan energi terbarukan dan efisiensi operasional untuk mengurangi jejak karbon perusahaan.'],
                ['icon' => 'Shield', 'title' => 'Keselamatan & K3', 'desc' => 'Komitmen zero accident dengan implementasi sistem manajemen keselamatan berstandar internasional ISO 45001.'],
            ]), 'content_type' => 'json'],
            ['section' => 'business', 'content_key' => 'cards', 'lang' => 'en', 'content_value' => json_encode([
                ['icon' => 'Pickaxe', 'title' => 'Exploration', 'desc' => 'Identification and mapping of coal reserves using the latest geological technology to ensure optimal resources.'],
                ['icon' => 'Factory', 'title' => 'Production', 'desc' => 'Efficient open-pit mining operations using modern heavy equipment with a focus on safety aspects.'],
                ['icon' => 'TrendingUp', 'title' => 'Sales & Distribution', 'desc' => 'Strong domestic and export distribution networks, serving the energy needs of industries and power plants.'],
                ['icon' => 'Leaf', 'title' => 'Environmental Reclamation', 'desc' => 'Active post-mining land rehabilitation and reclamation programs, restoring ecosystem functions optimally.'],
                ['icon' => 'Zap', 'title' => 'Energy Efficiency', 'desc' => 'Innovation in renewable energy use and operational efficiency to reduce the company\'s carbon footprint.'],
                ['icon' => 'Shield', 'title' => 'Safety & K3', 'desc' => 'Zero accident commitment with implementation of internationally standard ISO 45001 safety management system.'],
            ]), 'content_type' => 'json'],

            // ===== STATS SECTION =====
            ['section' => 'stats', 'content_key' => 'eyebrow', 'lang' => 'id', 'content_value' => 'Pencapaian Kami', 'content_type' => 'text'],
            ['section' => 'stats', 'content_key' => 'eyebrow', 'lang' => 'en', 'content_value' => 'Our Achievements', 'content_type' => 'text'],
            ['section' => 'stats', 'content_key' => 'title', 'lang' => 'id', 'content_value' => 'Angka yang', 'content_type' => 'text'],
            ['section' => 'stats', 'content_key' => 'title', 'lang' => 'en', 'content_value' => 'Numbers that', 'content_type' => 'text'],
            ['section' => 'stats', 'content_key' => 'titleHighlight', 'lang' => 'id', 'content_value' => 'Berbicara', 'content_type' => 'text'],
            ['section' => 'stats', 'content_key' => 'titleHighlight', 'lang' => 'en', 'content_value' => 'Speak', 'content_type' => 'text'],
            ['section' => 'stats', 'content_key' => 'desc', 'lang' => 'id', 'content_value' => 'Dua dekade komitmen dalam industri pertambangan, tercermin dalam angka-angka pencapaian kami.', 'content_type' => 'text'],
            ['section' => 'stats', 'content_key' => 'desc', 'lang' => 'en', 'content_value' => 'Two decades of commitment in the mining industry, reflected in our achievement figures.', 'content_type' => 'text'],
            ['section' => 'stats', 'content_key' => 'items', 'lang' => 'id', 'content_value' => json_encode([
                ['value' => 20, 'suffix' => '+', 'label' => 'Tahun Beroperasi', 'desc' => 'Berpengalaman sejak awal 2000-an'],
                ['value' => 15, 'suffix' => 'M+', 'label' => 'Ton Produksi', 'desc' => 'Produksi kumulatif batu bara'],
                ['value' => 500, 'suffix' => '+', 'label' => 'Karyawan', 'desc' => 'Tenaga kerja profesional'],
                ['value' => 12, 'suffix' => 'K+', 'label' => 'Ha Rehabilitasi', 'desc' => 'Lahan yang telah direklamasi'],
            ]), 'content_type' => 'json'],
            ['section' => 'stats', 'content_key' => 'items', 'lang' => 'en', 'content_value' => json_encode([
                ['value' => 20, 'suffix' => '+', 'label' => 'Years of Operation', 'desc' => 'Experienced since the early 2000s'],
                ['value' => 15, 'suffix' => 'M+', 'label' => 'Tons Produced', 'desc' => 'Cumulative coal production'],
                ['value' => 500, 'suffix' => '+', 'label' => 'Employees', 'desc' => 'Professional workforce'],
                ['value' => 12, 'suffix' => 'K+', 'label' => 'Ha Rehabilitated', 'desc' => 'Land that has been reclaimed'],
            ]), 'content_type' => 'json'],

            // ===== CAREERS SECTION =====
            ['section' => 'careers', 'content_key' => 'eyebrow', 'lang' => 'id', 'content_value' => 'Karir', 'content_type' => 'text'],
            ['section' => 'careers', 'content_key' => 'eyebrow', 'lang' => 'en', 'content_value' => 'Careers', 'content_type' => 'text'],
            ['section' => 'careers', 'content_key' => 'title', 'lang' => 'id', 'content_value' => 'Bergabunglah dengan', 'content_type' => 'text'],
            ['section' => 'careers', 'content_key' => 'title', 'lang' => 'en', 'content_value' => 'Join', 'content_type' => 'text'],
            ['section' => 'careers', 'content_key' => 'titleHighlight', 'lang' => 'id', 'content_value' => 'Tim Kami', 'content_type' => 'text'],
            ['section' => 'careers', 'content_key' => 'titleHighlight', 'lang' => 'en', 'content_value' => 'Our Team', 'content_type' => 'text'],
            ['section' => 'careers', 'content_key' => 'desc', 'lang' => 'id', 'content_value' => 'Kami selalu mencari individu berbakat dan berdedikasi untuk berkontribusi dalam misi kami membangun energi Indonesia yang berkelanjutan.', 'content_type' => 'text'],
            ['section' => 'careers', 'content_key' => 'desc', 'lang' => 'en', 'content_value' => 'We are always looking for talented and dedicated individuals to contribute to our mission of building sustainable energy for Indonesia.', 'content_type' => 'text'],
            ['section' => 'careers', 'content_key' => 'benefits', 'lang' => 'id', 'content_value' => json_encode([
                'Kompensasi Kompetitif', 'BPJS Kesehatan & Ketenagakerjaan', 'Pelatihan & Pengembangan', 'Tunjangan Lapangan',
            ]), 'content_type' => 'json'],
            ['section' => 'careers', 'content_key' => 'benefits', 'lang' => 'en', 'content_value' => json_encode([
                'Competitive Compensation', 'Health & Employment Insurance', 'Training & Development', 'Field Allowance',
            ]), 'content_type' => 'json'],

            // ===== CONTACT SECTION =====
            ['section' => 'contact', 'content_key' => 'eyebrow', 'lang' => 'id', 'content_value' => 'Kontak', 'content_type' => 'text'],
            ['section' => 'contact', 'content_key' => 'eyebrow', 'lang' => 'en', 'content_value' => 'Contact', 'content_type' => 'text'],
            ['section' => 'contact', 'content_key' => 'title', 'lang' => 'id', 'content_value' => 'Hubungi', 'content_type' => 'text'],
            ['section' => 'contact', 'content_key' => 'title', 'lang' => 'en', 'content_value' => 'Get in', 'content_type' => 'text'],
            ['section' => 'contact', 'content_key' => 'titleHighlight', 'lang' => 'id', 'content_value' => 'Kami', 'content_type' => 'text'],
            ['section' => 'contact', 'content_key' => 'titleHighlight', 'lang' => 'en', 'content_value' => 'Touch', 'content_type' => 'text'],
            ['section' => 'contact', 'content_key' => 'desc', 'lang' => 'id', 'content_value' => 'Kami siap membantu Anda. Kirimkan pesan dan tim kami akan merespons dalam 1-2 hari kerja.', 'content_type' => 'text'],
            ['section' => 'contact', 'content_key' => 'desc', 'lang' => 'en', 'content_value' => 'We are ready to help you. Send a message and our team will respond within 1-2 business days.', 'content_type' => 'text'],
            ['section' => 'contact', 'content_key' => 'address', 'lang' => 'id', 'content_value' => "Jl. Tambang-Site Kideco Jaya Agung\nKec. Batu Sopang, Kalimantan Timur,\nIndonesia 76211", 'content_type' => 'text'],
            ['section' => 'contact', 'content_key' => 'address', 'lang' => 'en', 'content_value' => "Jl. Tambang-Site Kideco Jaya Agung\nKec. Batu Sopang, East Kalimantan,\nIndonesia 76211", 'content_type' => 'text'],
            ['section' => 'contact', 'content_key' => 'phone', 'lang' => 'id', 'content_value' => '+62 542 000 000', 'content_type' => 'text'],
            ['section' => 'contact', 'content_key' => 'phone', 'lang' => 'en', 'content_value' => '+62 542 000 000', 'content_type' => 'text'],
            ['section' => 'contact', 'content_key' => 'email', 'lang' => 'id', 'content_value' => 'info@ptk2b.com', 'content_type' => 'text'],
            ['section' => 'contact', 'content_key' => 'email', 'lang' => 'en', 'content_value' => 'info@ptk2b.com', 'content_type' => 'text'],

            // ===== FOOTER =====
            ['section' => 'footer', 'content_key' => 'tagline', 'lang' => 'id', 'content_value' => 'Energi untuk Indonesia yang Berkelanjutan', 'content_type' => 'text'],
            ['section' => 'footer', 'content_key' => 'tagline', 'lang' => 'en', 'content_value' => 'Energy for a Sustainable Indonesia', 'content_type' => 'text'],
            ['section' => 'footer', 'content_key' => 'address', 'lang' => 'id', 'content_value' => 'Jl. Tambang-Site Kideco Jaya Agung, Kec. Batu Sopang, Kalimantan Timur, Indonesia', 'content_type' => 'text'],
            ['section' => 'footer', 'content_key' => 'address', 'lang' => 'en', 'content_value' => 'Jl. Pertambangan Raya No. 1, East Kalimantan, Indonesia', 'content_type' => 'text'],
            ['section' => 'footer', 'content_key' => 'phone', 'lang' => 'id', 'content_value' => '+62 812-5108-4891', 'content_type' => 'text'],
            ['section' => 'footer', 'content_key' => 'phone', 'lang' => 'en', 'content_value' => '+62 812-5108-4891', 'content_type' => 'text'],
            ['section' => 'footer', 'content_key' => 'email', 'lang' => 'id', 'content_value' => 'info@ptk2b.com', 'content_type' => 'text'],
            ['section' => 'footer', 'content_key' => 'email', 'lang' => 'en', 'content_value' => 'info@ptk2b.com', 'content_type' => 'text'],
        ];

        foreach ($contents as $content) {
            SiteContent::updateOrCreate(
                [
                    'section'     => $content['section'],
                    'content_key' => $content['content_key'],
                    'lang'        => $content['lang'],
                ],
                $content
            );
        }
    }
}
