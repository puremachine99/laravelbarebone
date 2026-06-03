<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class EmployeesSeeder extends Seeder
{
    public function run(): void
    {

        $departmentNames = [
            'BoD/BoA/C-Level',
            'Corporate Secretariat',
            'Finance',
            'People Culture and Strategy',
            'Product and RDI',
            'Revenue and Growth',
            'Technology',
        ];

        $departments = [];
        foreach ($departmentNames as $name) {
            $id = (string) Str::uuid();
            DB::table('departments')->insertOrIgnore([
                'id' => $id,
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            $departments[$name] = DB::table('departments')->where('name', $name)->value('id');
        }

        $positionData = [

            ['Chief Executive Officer',                                          'Struktural'],
            ['Chief Business Officer',                                           'Struktural'],
            ['Chief Operating Officer',                                          'Struktural'],
            ['Direksi',                                                          'Struktural'],
            ['Komisaris Utama',                                                  'Struktural'],
            ['Komisaris Bidang Riset dan Kerjasama',                             'Struktural'],
            ['Komisaris Bidang IT, HC, QA. Keuangan dan Umum',                  'Struktural'],
            ['Dewan Penasihat',                                                  'Struktural'],
            ['Corporate Secretary',                                              'Struktural'],
            ['Plt. Manager of Corporate Legal',                                  'Struktural'],
            ['Manager of Disbursement',                                          'Struktural'],
            ['Manager of Funding',                                               'Struktural'],
            ['SPV Iman, Imun, dan Aman',                                         'Struktural'],
            ['Head of Finance',                                                  'Struktural'],
            ['Plt. Manager of Finance',                                          'Struktural'],
            ['Plt. Manager of Asset and General Affairs',                        'Struktural'],
            ['Manager of Corporate Strategy and Performance Management and Plt. Manager of People and Culture', 'Struktural'],
            ['Head of People, Culture & Strategy',                               'Struktural'],
            ['Manager of Executive Education',                                   'Struktural'],
            ['Manager of Online Course',                                         'Struktural'],
            ['Manager of Saas and AI',                                           'Struktural'],
            ['Manager of Consulting Service',                                    'Struktural'],
            ['Head of Product and RDI',                                          'Struktural'],
            ['Manager of Global Expansion',                                      'Struktural'],
            ['Vice President of Revenue and Growth',                             'Struktural'],
            ['Manager of Digital Revenue and Marketing',                         'Struktural'],
            ['Coordinator of Digital Creative',                                  'Struktural'],
            ['Manager of Technology',                                            'Struktural'],

            ['Product Consultant Ahli',           'Fungsional'],
            ['Product Consultant Madya',          'Fungsional'],
            ['Product Consultant Muda',           'Fungsional'],
            ['Product Consultant Pratama',        'Fungsional'],
            ['CEO Assistant Junior',              'Fungsional'],
            ['CEO Driver',                        'Fungsional'],
            ['Legal Counsel Staff Junior',        'Fungsional'],
            ['Public Relation Senior',            'Fungsional'],
            ['Technical Secretary Officer Senior', 'Fungsional'],
            ['Technical Secretary Officer Junior', 'Fungsional'],
            ['Office Boy',                        'Fungsional'],
            ['Project Administration Officer Pratama', 'Fungsional'],
            ['Project Administration Officer Madya',   'Fungsional'],
            ['Accounting Specialist Junior',      'Fungsional'],
            ['Tax Specialist Junior',             'Fungsional'],
            ['Financial Analyst Junior',          'Fungsional'],
            ['Funding Officer Senior',            'Fungsional'],
            ['Asset and General Affairs Officer Junior', 'Fungsional'],
            ['Administrative Officer Junior',     'Fungsional'],
            ['Payment Specialist Junior',         'Fungsional'],
            ['Security',                          'Fungsional'],
            ['Chef',                              'Fungsional'],
            ['People and Culture Officer Senior', 'Fungsional'],
            ['People and Culture Officer Junior', 'Fungsional'],
            ['Corporate Strategy and Performance Management Analyst Junior', 'Fungsional'],
            ['Consultant Relation Officer Junior', 'Fungsional'],
            ['Consultant Relation Officer Senior', 'Fungsional'],
            ['Online Course Officer Junior',      'Fungsional'],
            ['SaaS and AI Product Research Junior', 'Fungsional'],
            ['SaaS and AI Product Research Senior', 'Fungsional'],
            ['QA Specialist and Product Research Muda', 'Fungsional'],
            ['Graphic Designer Senior',           'Fungsional'],
            ['Graphic Designer Junior',           'Fungsional'],
            ['Production Specialist Junior',      'Fungsional'],
            ['Production Specialist Senior',      'Fungsional'],
            ['Executive Education Officer Junior', 'Fungsional'],
            ['Global Expert Specialist Junior',   'Fungsional'],
            ['Department Secretary',              'Fungsional'],
            ['Partnership Management Officer Pratama', 'Fungsional'],
            ['Partnership Management Officer Ahli',    'Fungsional'],
            ['Event and Program Specialist Senior', 'Fungsional'],
            ['Content Creator Senior',            'Fungsional'],
            ['Copywriter Senior',                 'Fungsional'],
            ['Copywriter Junior',                 'Fungsional'],
            ['Videographer and Editor Senior',    'Fungsional'],
            ['Digital Revenue and Marketing Officer Junior', 'Fungsional'],
            ['Performance Marketing Specialist Senior', 'Fungsional'],
            ['AI Engineer Senior',                'Fungsional'],
            ['AI Engineer Junior',                'Fungsional'],
            ['Programmer Senior',                 'Fungsional'],
            ['Programmer Junior',                 'Fungsional'],
            ['Development and Operations Engineer Senior', 'Fungsional'],
            ['Project Manager Junior',            'Fungsional'],
            ['System Analyst Senior',             'Fungsional'],
            ['Quality Assurance Specialist Junior', 'Fungsional'],
            ['User Interface/Experience Designer Junior', 'Fungsional'],
            ['Advisor Pengembangan',              'Fungsional'],
        ];

        foreach ($positionData as [$name, $type]) {
            DB::table('positions')->insertOrIgnore([
                'id' => (string) Str::uuid(),
                'name' => $name,
                'type' => $type,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $deptId = fn (string $name) => DB::table('departments')->where('name', $name)->value('id');
        $posId = fn (string $name) => DB::table('positions')->where('name', $name)->value('id');

        $parseStatus = function (string $raw): array {
            $parts = array_map('trim', explode(',', $raw));
            $status = $parts[0] ?? null;
            $isCltp = in_array('CLTP', $parts, true);
            $validStatuses = ['PKWT', 'PKWTT', 'Internship', 'Mitra Strategis', 'Bond Agreement'];
            if (! in_array($status, $validStatuses, true)) {
                $status = null;
            }

            return [$status, $isCltp];
        };

        $parseDate = function (?string $d): ?string {
            if (! $d) {
                return null;
            }
            $dt = \DateTime::createFromFormat('d/m/Y', trim($d));

            return $dt ? $dt->format('Y-m-d') : null;
        };

        $parseMarital = function (string $raw): string {
            return match (true) {
                str_contains($raw, 'Belum') => 'Lajang',
                default => 'Menikah',
            };
        };

        $rows = [
            ['Oscar Radyan Danar, S.AP., M.AP., Ph.D.', 'BoD/BoA/C-Level', 'Chief Executive Officer', 'Product Consultant Ahli', null, 'PKWTT', null, null, '19870516202001001', '3512061605870003', '34.240.155.1-656.000', 'S3', '16/5/1987', 'Laki-laki', '6281232781673', 'oscar@ub.ac.id', 'Menikah'],
            ['Akhmad Amirudin, S.AP., M.AP., M.Pol.Sc., Ph.D.', 'BoD/BoA/C-Level', 'Chief Business Officer', 'Product Consultant Ahli', null, 'PKWTT', null, null, '19870426202001006', '3516122604870001', '45.550.028.0-602.000', 'S3', '26/4/1987', 'Laki-laki', '6281333555026', 'akhmadamirudin@gmail.com', 'Menikah'],
            ['Ahmad Miftakhul Khoiri, S.AP., M.AP.', 'BoD/BoA/C-Level', 'Direksi', 'Product Consultant Madya', null, 'PKWTT', null, null, '19910917202001003', '3523171709910003', '71.442.634.3-652.000', 'S2', '17/9/1991', 'Laki-laki', '6285736343452', 'khoirimiftakh@gmail.com', 'Menikah'],
            ['Damas Dwi Anggoro, S.AB., MA.', 'BoD/BoA/C-Level', 'Direksi', 'Product Consultant Madya', null, 'PKWTT', null, null, '19890626202001004', '3317102606890001', '45.826.376.1-507.000', 'S2', '26/6/1989', 'Laki-laki', '6285640583344', 'damasdwia@gmail.com', 'Menikah'],
            ['Suhartono Winoto, S.AP., M.AP.', 'BoD/BoA/C-Level', 'Direksi', 'Product Consultant Madya', null, 'PKWTT', null, null, '19890808202001002', '3574010808890004', '76.521.187.5-625.000', 'S2', '8/8/1989', 'Laki-laki', '6285230298323', 'ewinparadiddle@gmail.com', 'Menikah'],
            ['Yoky Tulus Sabtian, S.AP., M.AP.', 'BoD/BoA/C-Level', 'Direksi', null, null, 'PKWTT', null, null, '19860628202001005', '3577022806860001', '94.883.729.9-621.000', 'S2', '28/6/1986', 'Laki-laki', '6285204768829', 'okytulus@yahoo.co.id', 'Menikah'],
            ['Mukhamad Taufiq Setiawan, S.AB., M.AB.', 'BoD/BoA/C-Level', 'Direksi', 'Product Consultant Madya', null, 'PKWTT', null, null, '19861203202001007', '3323060312860001', '94.956.769.7-652.000', 'S2', '3/12/1986', 'Laki-laki', '6282135250901', 'mtaufiqsetiawan@gmail.com', 'Menikah'],
            ['Prof. Dr. M. R. Khairul Muluk, M.Si.', 'BoD/BoA/C-Level', 'Komisaris Utama', 'Product Consultant Ahli', null, 'PKWTT', null, null, '19710510202001009', '3573031005710007', '68.523.314.0-623.000', 'S3', '10/5/1971', 'Laki-laki', '628123313631', 'khairulmulukfiaub@gmail.com', 'Menikah'],
            ['Asti Amelia Novita, S.AP., M.AP., Ph.D.', 'BoD/BoA/C-Level', 'Komisaris Bidang Riset dan Kerjasama', null, null, 'PKWTT', null, null, '19851110202001010', '3573055011850001', null, 'S3', '10/11/1985', 'Perempuan', '6281232886030', 'asti@ub.ac.id', 'Menikah'],
            ['Ir. Sutrisno, M.T.', 'BoD/BoA/C-Level', 'Komisaris Bidang IT, HC, QA. Keuangan dan Umum', null, null, 'PKWTT', null, null, '19570325202001011', '3573052503570003', null, 'S2', '25/3/1957', 'Laki-laki', '6281249542011', 'trisno@ub.ac.id', 'Menikah'],
            ['Prof. Dr. Bambang Supriyono, M.S.', 'BoD/BoA/C-Level', 'Dewan Penasihat', 'Product Consultant Ahli', '1/10/2022', 'PKWTT', null, null, '19610905202210234', '3573020509610002', '08.723.207.0-623.000', 'S3', '5/9/1961', 'Laki-laki', '6282132923470', 'bambangsupriyono@ub.ac.id', 'Menikah'],
            ['Laily Akbariah, S.AP., M.AP.', 'BoD/BoA/C-Level', 'Chief Operating Officer', null, '1/3/2020', 'PKWTT', null, null, '19900708202003012', '3575024807900001', '96.795.549.3-652.000', 'S2', '8/7/1990', 'Perempuan', '6282231909852', 'laily.akbariah90@gmail.com', 'Menikah'],

            ['Affan Sandi, S.AP.', 'Corporate Secretariat', null, 'CEO Assistant Junior', '1/2/2023', 'PKWTT', '1/12/2022', null, '19980708202302241', '3513090807980002', '60.841.621.0-625.000', 'S1', '8/7/1998', 'Laki-laki', '6283152684588', 'apansan08@gmail.com', 'Menikah'],
            ['Affina Mustika Kasranti', 'Corporate Secretariat', null, 'CEO Assistant Junior', '6/1/2024', 'Internship', '3/12/2025', '3/3/2026', '20031025202406357', '3276026410030002', '19.980.502.9-652.000', 'Menempuh S1', '25/10/2003', 'Perempuan', '62895604467481', 'affinamk@student.ub.ac.id', 'Belum Menikah'],
            ['Anggita Nurhaliza Yasmin, A.Md.Bns.', 'Corporate Secretariat', null, 'CEO Assistant Junior', '9/9/2024', 'PKWTT', '2/1/2026', null, '20020720202409405', '3506066007020001', '12.479.064.3-652.000', 'D-III', '20/7/2002', 'Perempuan', '6281938593348', 'gitayasmin.ak@gmail.com', 'Menikah'],
            ['Ghaffar Jabar Akbar Iwan', 'Corporate Secretariat', null, 'CEO Driver', '21/8/2025', 'PKWTT', '22/11/2025', null, '20010410202508480', '3573031004010004', null, null, '10/4/2001', 'Laki-laki', '6282124421544', 'ghaffar.jabar91@gmail.com', 'Menikah'],
            ['Nuriyanna, S.E., M.M.', 'Corporate Secretariat', 'Corporate Secretary', null, '6/10/2021', 'PKWTT', null, null, '19960831202110083', '3573057108960001', '60.050.376.7-652.000', 'S2', '3/6/2003', 'Perempuan', '6281364997555', 'nuriyanna31@gmail.com', 'Menikah'],
            ['Qowiyyun Amin, S.H.', 'Corporate Secretariat', null, 'Legal Counsel Staff Junior', '1/7/2025', 'PKWT', '1/7/2025', '1/8/2026', '20030603202507465', '3578020306030001', null, 'S1', '3/6/2003', 'Laki-laki', '6287843938033', 'qowiyyunamin123@gmail.com', 'Belum Menikah'],
            ['Shelma Salsabila, S.I.Kom.', 'Corporate Secretariat', null, 'Public Relation Senior', '11/10/2021', 'PKWTT', '1/8/2024', null, '19980701202110087', '5108024107980018', '50.491.355.9-902.000', 'S1', '1/7/1998', 'Perempuan', '6281246275802', 'shesalsabila14@gmail.com', 'Belum Menikah'],
            ['Syirli Ramadhannia, S.P.', 'Corporate Secretariat', null, 'Technical Secretary Officer Senior', '8/4/2022', 'PKWTT', '1/11/2024', null, '19990103202204194', '3208324301990001', '50.972.015.7-657.000', 'S1', '7/1/2001', 'Perempuan', '6283823363072', 'syirliramadhannia@gmail.com', 'Menikah'],
            ['Tri Puspita Sari, S.IP.', 'Corporate Secretariat', null, 'Technical Secretary Officer Junior', '16/6/2025', 'PKWT', '1/10/2025', '1/10/2026', '20010107202506462', '3523184701010003', null, 'S1', '7/1/2001', 'Perempuan', '6289678906744', 'tripuspitasari16@gmail.com', 'Belum Menikah'],
            ['Wahyu Suryo Ajie, S.H.', 'Corporate Secretariat', 'Plt. Manager of Corporate Legal', null, '1/12/2022', 'PKWTT', '1/7/2023', null, '19980622202212235', '3275122206980005', '62.220.652.2-652.000', 'S1', '22/6/1998', 'Laki-laki', '6287756660948', 'wahyusuryoajie6@gmail.com', 'Menikah'],

            ['Abd Aziz', 'Finance', null, 'Office Boy', '15/7/2024', 'PKWT', '1/10/2024', '1/11/2026', '19870907202407369', '3573010709870007', '76.390.080.0-652.000', 'PKBM / SMA', '7/9/1987', 'Laki-laki', '6282314076966', 'abdulaziz6586@gmail.com', 'Menikah'],
            ['Affiani Novilasari, S.AP.', 'Finance', null, 'Project Administration Officer Pratama', '1/6/2024', 'PKWT, CLTP', '2/10/2025', '2/10/2026', '19991105202406351', '3518064511990002', '20.380.792.0-652.000', 'S1', '5/11/1999', 'Perempuan', '6281328280224', 'fianovila99@gmail.com', 'Belum Menikah'],
            ['Agustina Novita Sari, S.E.', 'Finance', null, 'Accounting Specialist Junior', '2/9/2024', 'PKWT', '2/1/2026', '2/1/2027', '20010812202409397', '3507085208010004', '12.472.393.3-654.000', 'S1', '12/8/2001', 'Perempuan', '6282232429908', 'novitawidya21@gmail.com', 'Belum Menikah'],
            ['Alfian Royvaldo Aryo Wibowo, S.Pn.', 'Finance', null, 'Project Administration Officer Madya', '8/5/2023', 'PKWTT', '1/9/2024', null, '20010325202305274', '3311042503010006', null, 'S1', '25/3/2001', 'Laki-laki', '62895422934484', 'alfian.royvaldo@gmail.com', 'Belum Menikah'],
            ['Amanda Luthfi Indrasari, S.I.Kom.', 'Finance', null, 'Project Administration Officer Pratama', '1/6/2024', 'PKWT', '2/10/2025', '2/10/2026', '20010228202406352', '3515086802010002', '20.845.652.5-617.000', 'S1', '28/2/2001', 'Perempuan', '6282139317457', 'amandaluthfii28@gmail.com', 'Belum Menikah'],
            ['Annisa Widya Nugraheni, S.AB.', 'Finance', null, 'Project Administration Officer Pratama', '10/7/2023', 'PKWT', '2/11/2025', '2/2/2026', '19980606202307280', '3518134407980003', '62.740.390.0-655.000', 'S1', '6/6/1998', 'Perempuan', '6281559998288', 'anisawidya57@gmail.com', 'Belum Menikah'],
            ['Chanira Nuansa Bunga, S.I.Kom., M.I.Kom.', 'Finance', null, 'Funding Officer Senior', '19/1/2026', 'Bond Agreement', '19/1/2026', '19/4/2026', '19880809202601486', '3573034908880003', null, 'S2', '9/8/1988', 'Perempuan', '6281333151202', 'chanira.nb@gmail.com', 'Menikah'],
            ['Diva Noer Alfalinni, S.AP.', 'Finance', null, 'Project Administration Officer Madya', '8/5/2023', 'PKWTT', '1/10/2025', null, '19990925202305275', '3303046509990003', null, 'S1', '25/9/1999', 'Perempuan', '6289665621490', 'divaalfalinni25@gmail.com', 'Belum Menikah'],
            ['Farhan Hamid', 'Finance', null, 'Tax Specialist Junior', '1/6/2024', 'PKWT', '2/10/2025', '2/10/2026', '20011210202406355', '6471051012010009', '20.834.751.8-652.000', 'Menempuh S1', '10/12/2001', 'Laki-laki', '6285794047095', 'farhaanhamiid@gmail.com', 'Belum Menikah'],
            ['Febi Melani Dewi, S.AP.', 'Finance', null, 'Project Administration Officer Madya', '1/2/2023', 'PKWTT', '1/12/2023', null, '20000205202302238', '3518164502000004', '39.004.501.1-655.000', 'S1', '5/2/2000', 'Perempuan', '6281459090875', 'febimelani05022000@gmail.com', 'Belum Menikah'],
            ['Hasyimi Ulil Abshor, S.Mat.', 'Finance', null, 'Financial Analyst Junior', '19/8/2024', 'PKWT', '2/1/2026', '2/1/2027', '20020126202408396', '3314092601020001', '21.060.426.0-652.000', 'S1', '26/1/2002', 'Laki-laki', '6288216618154', 'ulil.and.h@gmail.com', 'Belum Menikah'],
            ['Husein Rizqi, A.Md.Kom.', 'Finance', 'Plt. Manager of Asset and General Affairs', null, '9/1/2023', 'PKWTT', '1/12/2023', null, '19961021202301236', '3573022110960003', '62.431.403.5-623.000', 'D-III', '21/10/1996', 'Laki-laki', '6281934310021', 'huseinrizqi96@gmail.com', 'Belum Menikah'],
            ['Ichlasul Rizki Johansyah Santosa, S.IP.', 'Finance', null, 'Asset and General Affairs Officer Junior', '25/11/2022', 'PKWT', '1/10/2024', '1/11/2026', '19990818202211224', '3578101808990005', '50.772.856.6.619.000', 'S1', '18/8/1999', 'Laki-laki', '6285781607054', 'ichlasulrizkijs@gmail.com', 'Belum Menikah'],
            ['Jasminne Fridayanti Putri Safrida, S.I.P.', 'Finance', null, 'Project Administration Officer Pratama', '1/4/2024', 'PKWT', '2/8/2025', '2/9/2026', '20020503202404328', '6473024305020006', null, 'S1', '3/5/2002', 'Perempuan', '6281254457129', 'JASMINNEFRIDAYANTI22@GMAIL.COM', 'Belum Menikah'],
            ['Jilan Nuril Azizah, S.T.', 'Finance', null, 'Project Administration Officer Madya', '1/8/2022', 'PKWTT', '1/10/2024', null, '19981109202208200', '3508034911980003', '39.001.112.0-652.000', 'S1', '9/11/1998', 'Perempuan', '6281936396944', 'jilannuril@gmail.com', 'Belum Menikah'],
            ['Joko Firman Prasetiyo Adi', 'Finance', null, 'Security', '1/9/2024', 'PKWT', '2/10/2025', '2/11/2026', '19860516202409395', '3516091605860002', null, null, '16/5/1986', 'Laki-laki', '6285755737943', null, 'Menikah'],
            ['Kurnia Eka Setiyawati, S.M.', 'Finance', null, 'Administrative Officer Junior', '2/1/2026', 'PKWT', null, null, '19980615202601484', '3506225506980001', '61.756.638.5-655.000', 'S1', '15/6/1998', 'Perempuan', '6281259829657', 'kurniaekasetiyawati@gmail.com', 'Belum Menikah'],
            ['Mellenefea Setia Ardinda, S.Si.', 'Finance', null, 'Project Administration Officer Pratama', '1/4/2024', 'PKWT', '2/8/2025', '2/9/2026', '20000105202404329', '3517134501000001', '39.160.850.2-649.000', 'S1', '5/1/2000', 'Perempuan', '6285815020052', 'MELLENEFEAA@GMAIL.COM', 'Belum Menikah'],
            ['Muhammad Robby, S.Pd.', 'Finance', null, 'Asset and General Affairs Officer Junior', '1/8/2023', 'PKWT', '1/2/2024', '1/3/2026', '20000829202308284', '3527032908000003', '43.022.946.8-657.000', 'S1', '29/8/2000', 'Laki-laki', '6285156568073', 'muhammadrobby3912.2045@gmail.com', 'Menikah'],
            ['Nandita Farahdya Hariyono, S.Pd.', 'Finance', null, 'Project Administration Officer Pratama', '1/6/2024', 'PKWT', '2/10/2025', '2/10/2026', '20000215202406353', '3573015502000012', '60.423.854.3-652.000', 'S1', '15/2/2000', 'Perempuan', '6283898542215', 'farahnandita9@gmail.com', 'Belum Menikah'],
            ['Novita Finardi, S.AP.', 'Finance', 'Manager of Disbursement', null, '15/5/2021', 'PKWTT', '1/12/2023', null, '19981111202105067', '3507095111980002', '63.963.083.9-654.000', 'S1', '11/11/1998', 'Perempuan', '6288235270973', 'novitafinardi@gmail.com', 'Belum Menikah'],
            ['Putri Caniagy Lucky, S.E.', 'Finance', 'Manager of Funding', null, '9/9/2024', 'PKWTT', '9/12/2024', null, '19880412202409401', '3573015204880009', '46.511.783.6-652.000', 'S1', '12/4/1988', 'Perempuan', '6281233235905', 'putrichaniagy12@gmail.com', 'Menikah'],
            ['Radang Redjeki Mardjo Saputro', 'Finance', 'SPV Iman, Imun, dan Aman', null, null, 'PKWTT', null, null, '19600920202003431', '3509202009600002', null, null, '20/9/1960', 'Laki-laki', '628818113473', 'radangredjekim@gmail.com', 'Menikah'],
            ['Rukiah, S.T.', 'Finance', null, 'Project Administration Officer Madya', '10/7/2023', 'PKWTT', '1/10/2024', null, '19981124202307282', '3507226411990001', '63.354.383.0-657.000', 'S1', '24/11/1998', 'Perempuan', '628974255420', 'rukiahkiki@gmail.com', 'Belum Menikah'],
            ['Sekar Sari Agustina, S.M.', 'Finance', null, 'Payment Specialist Junior', '7/3/2024', 'PKWTT', '1/4/2025', null, '19980809202403318', '3501104908980001', '12.806.783.2-657.000', 'S1', '9/8/1998', 'Perempuan', '6285233983897', 'sekarsariagustina9@gmail.com', 'Belum Menikah'],
            ['Shalsabilla Naura Sakinah, S.Tr.Ak., CAAT.', 'Finance', 'Plt. Manager of Finance', null, '1/9/2022', 'PKWTT', '1/2/2024', null, '20010806202209203', '3573014608010007', '39.592.850.0-623.000', 'D-IV', '6/8/2001', 'Perempuan', '6282232785605', 'shasabillans06@gmail.com', 'Belum Menikah'],
            ['Samsul', 'Finance', null, 'Office Boy', '1/3/2020', 'PKWT', '1/1/2026', '1/1/2027', '19750815202003417', '3507041508750004', null, null, '15/8/1975', 'Laki-laki', '6281231094170', null, 'Menikah'],
            ['Taufiq Tri Jakanugraha, S.E., CIMM.', 'Finance', 'Head of Finance', null, '1/11/2022', 'PKWTT', null, null, '19900202202211217', '3573040202900003', '98.473.377.4-623.000', 'S1', '2/2/1990', 'Laki-laki', '6282131430076', 'tjakanugraha@gmail.com', 'Menikah'],
            ['Widodo', 'Finance', null, 'Security', '10/7/2025', 'PKWT', '10/7/2025', '10/8/2026', '19850210202507470', '3573051002850008', null, null, '10/2/1985', 'Laki-laki', '6289615103354', null, 'Menikah'],
            ['Wiwik Ernawati', 'Finance', null, 'Chef', '1/3/2020', 'PKWT', '2/10/2025', '2/11/2026', '19840502202003416', '3507064205840004', null, null, '2/5/1984', 'Perempuan', '6281233458929', null, 'Menikah'],

            ['Akhmad Rizqi Ibnusholikhin, S.E.', 'People Culture and Strategy', 'Manager of Corporate Strategy and Performance Management and Plt. Manager of People and Culture', null, '14/2/2022', 'PKWTT', '1/10/2022', null, '19911225202202155', '3506172512910002', '74.806.253.6-655.000', 'S1', '25/12/1991', 'Laki-laki', '6281329186225', 'akhmadrizqi.ibnu@gmail.com', 'Menikah'],
            ['Angelina Ayu Ning Tyas', 'People Culture and Strategy', null, 'Administrative Officer Junior', '8/9/2025', 'PKWT', '1/9/2025', '1/10/2026', '19850502202509478', '3573034205850009', '08.730.208.9-623.000', 'SMK', '2/5/1985', 'Perempuan', '6282245526584', 'angelina.ayu.nt@gmail.com', 'Belum Menikah'],
            ['Farida Kusuma Wardhani, S.T., M.M.', 'People Culture and Strategy', 'Head of People, Culture & Strategy', null, '24/1/2022', 'PKWTT', null, null, '19960428202201101', '3271066804960018', '81.297.243.8-404.000', 'S2', '28/4/1996', 'Perempuan', '6285695214934', 'faridakusumaw@gmail.com', 'Menikah'],
            ['Hafizul Wahdi, S.IP.', 'People Culture and Strategy', null, 'People and Culture Officer Senior', '1/11/2022', 'PKWTT', '1/4/2023', null, '19970106202211220', '3276060601970002', '96.119.074.1-448.000', 'S1', '6/1/1997', 'Laki-laki', '6282298079796', 'hafizulwahdi@gmail.com', 'Menikah'],
            ['Hikmah Tsurayya, S.Psi., CIRP.', 'People Culture and Strategy', null, 'People and Culture Officer Senior', '14/5/2025', 'PKWT', '14/5/2025', '7/8/2026', '19950927202505452', '3515156709950002', '83.789.209.0-622.000', 'S1', '27/9/1995', 'Perempuan', '6287771174853', 'hikmahtsurayya@gmail.com', 'Menikah'],
            ['Muhammad Ivan Riyandhika Saad Psf, S.P.W.K.', 'People Culture and Strategy', null, 'People and Culture Officer Junior', '20/3/2024', 'PKWT', '1/6/2024', '1/7/2026', '20010817202403319', '5204081708010002', '12.641.704.7-913.000', 'S1', '17/8/2001', 'Laki-laki', '6281239852797', 'rianivan17@gmail.com', 'Belum Menikah'],
            ['Nabila Farah Riasa, S.Psi.', 'People Culture and Strategy', null, 'Corporate Strategy and Performance Management Analyst Junior', '7/7/2025', 'PKWT', '7/7/2025', '7/8/2026', '19970602202507468', '3579014206970004', '43.680.656.6-628.000', 'S1', '2/6/1997', 'Perempuan', '6281515133921', 'nabilafriasa@gmail.com', 'Belum Menikah'],
            ['Nabila Gadis Amanda, S.I.P.', 'People Culture and Strategy', null, 'Corporate Strategy and Performance Management Analyst Junior', '2/1/2026', 'PKWTT', '2/1/2026', null, '19991001202601485', '3573054110990007', null, 'S1', '1/10/1999', 'Perempuan', '6289654531091', 'nbgaadis@gmail.com', 'Belum Menikah'],
            ['Putri Idhamaningati, S.AP.', 'People Culture and Strategy', null, 'Corporate Strategy and Performance Management Analyst Junior', '5/10/2023', 'PKWTT', '1/2/2025', '1/3/2026', '19990322202310292', '3523186203990001', '19.621.236.9-648.000', 'S1', '22/3/1999', 'Perempuan', '6282331671199', 'putriidhamaningati99@gmail.com', 'Belum Menikah'],
            ['Yulia Ayu Pradita, S.M.', 'People Culture and Strategy', null, 'Corporate Strategy and Performance Management Analyst Junior', '1/8/2025', 'PKWT', '1/8/2025', '1/9/2026', '19990725202508476', '3510056507990001', '60.499.622.3-627.000', 'S1', '25/7/1999', 'Perempuan', '6289697727365', 'yuliaapradita@gmail.com', 'Belum Menikah'],

            ['Aissyah Nabila Anjani, S.PWK.', 'Product and RDI', null, 'Product Consultant Pratama', '1/4/2023', 'PKWT', '2/8/2025', '2/9/2026', '19990814202304273', '3575035408990002', '63.219.675.4-624.000', 'S1', '14/8/1999', 'Perempuan', '6281331175104', 'aissyahnabilaanjani@gmail.com', 'Belum Menikah'],
            ['Aliya Nur Aziza, S.Sos.', 'Product and RDI', null, 'Consultant Relation Officer Junior', '1/4/2023', 'PKWT', '1/2/2025', '1/3/2026', '19990728202304267', '3573046807990003', '50.565.882.3-623.000', 'S1', '28/7/1999', 'Perempuan', '6285606213757', 'aliyaziza2807@gmail.com', 'Belum Menikah'],
            ['Andre Dwi Prasaja, S.I.P., M.Sc.', 'Product and RDI', null, 'Product Consultant Madya', '25/8/2021', 'PKWTT', '1/9/2024', null, '20000206202110082', '1602050602000002', '63.942.527.1-657.000', 'S2', '6/2/1999', 'Laki-laki', '628973787547', 'andreprasaja2000@gmail.com', 'Menikah'],
            ['Ariq Dhamas Salsabil, S.I.P.', 'Product and RDI', null, 'Product Consultant Pratama', '13/9/2021', 'PKWTT', '1/2/2023', null, '19990207202109081', '3404030702990001', '50.989.257.6-657.000', 'S1', '7/2/1999', 'Laki-laki', '6285157077515', 'ariqds1976@gmail.com', 'Belum Menikah'],
            ['Arsya Nisrina', 'Product and RDI', null, 'Online Course Officer Junior', '14/4/2025', 'PKWT', '1/2/2024', '14/5/2026', '20001113202504434', '3604055311000001', '19.967.418.5-652.000', 'Menempuh S1', '13/11/2000', 'Perempuan', '62895391586497', 'arsyanisrina@gmail.com', 'Belum Menikah'],
            ['Dr. Bevaola Kusumasari, M.Si.', 'Product and RDI', null, 'Product Consultant Ahli', '1/3/2023', 'Mitra Strategis', '1/10/2024', null, '19760520202303248', '3404076005760010', '45.424.811.3-542.000', 'S3', '20/5/1976', 'Perempuan', '628112584888', null, 'Menikah'],
            ['Daffa Faustina Widodo, S.AP.', 'Product and RDI', null, 'SaaS and AI Product Research Junior', '23/8/2022', 'PKWTT', '1/2/2024', null, '19991221202208204', '3571026112990001', '39.304.604.0-622.000', 'S1', '21/12/1999', 'Perempuan', '6285746557420', 'daffafaustina42@gmail.com', 'Belum Menikah'],
            ['Denis Taufik Tri Wibowo, S.IP.', 'Product and RDI', 'Manager of Executive Education', null, '1/4/2023', 'PKWTT', '1/10/2024', null, '20000905202304265', '3507260509000001', '99.122.780.2-657.000', 'S1', '5/9/2000', 'Laki-laki', '6285731245134', 'denistaufiktriwibowo@gmail.com', 'Belum Menikah'],
            ['Dimas Fakhri Al Khariri, S.P.W.K.', 'Product and RDI', null, 'Product Consultant Pratama', '14/4/2025', 'PKWT', '14/4/2025', '14/5/2026', '20020103202504438', '3507220301020001', '20.398.252.5-657.000', 'S1', '3/1/2002', 'Laki-laki', '6281952062486', 'dimasfakhri50@gmail.com', 'Belum Menikah'],
            ['Dinda Galuh Sudarsono Putri, S.AP.', 'Product and RDI', null, 'Product Consultant Muda', '1/11/2021', 'PKWTT', '1/8/2022', null, '19990306202110093', '3507135806990003', '63.802.195.6-654.000', 'S1', '18/6/1999', 'Perempuan', '6285816213480', 'gdinda589@gmail.com', 'Belum Menikah'],
            ['Ella Mardlotillah Khoirun Nisa\', S.AP.', 'Product and RDI', null, 'Global Expert Specialist Junior', '8/9/2025', 'Internship', '8/9/2025', '8/4/2026', '19980821202509479', '3573056108980006', '50.127.000.3-652.000', 'S1', '21/8/1998', 'Perempuan', '6283152485376', 'mardlotillahella@gmail.com', 'Belum Menikah'],
            ['Fahmi Prayoga, S.E.', 'Product and RDI', null, 'QA Specialist and Product Research Muda', '1/3/2020', 'PKWTT', '1/2/2025', null, '19980410202003019', '3522151004980002', '43.008.990.4-652.000', 'S1', '10/4/1998', 'Laki-laki', '6285225252500', 'fahmiefpe@gmail.com', 'Menikah'],
            ['Fahimah Fauwziyah, S.Stat., M.Stat.', 'Product and RDI', null, 'Consultant Relation Officer Junior', '1/10/2024', 'PKWT', '1/9/2025', '2/10/2026', '19980221202505446', '3507226102980001', '40.169.837.8-657.000', 'S2', '9/10/2001', 'Perempuan', '6285649761540', 'fahimah.ffa@gmail.com', 'Belum Menikah'],
            ['Fandy Yahya Pratama Putra, S.AP.', 'Product and RDI', null, 'Graphic Designer Senior', '1/8/2024', 'PKWTT', '1/2/2025', '1/3/2026', '19980216202201123', '3573021602980002', '60.771.885.5-623.000', 'S1', '16/2/1998', 'Laki-laki', '6285755961270', 'fandyhyahyapp@gmail.com', 'Belum Menikah'],
            ['Farhan Sidqi, S.P.W.K.', 'Product and RDI', null, 'Product Consultant Pratama', '14/4/2025', 'PKWT, CLTP', '1/5/2025', '14/5/2026', '20030115202504440', '1501041501030001', '20.948.857.6-652.000', 'S1', '28/7/2000', 'Laki-laki', '6282371370769', 'farhan.sidqi15@gmail.com', 'Belum Menikah'],
            ['Farkhana Laily, S.Pi., M.Sc.', 'Product and RDI', null, 'Production Specialist Junior', '8/7/2024', 'PKWT', '2/11/2025', '2/11/2026', '19991022202407366', '3401076210990001', '62.734.209.0-544.000', 'S2', '22/10/1999', 'Perempuan', '6281228132989', 'farkhana21laily@gmail.com', 'Belum Menikah'],
            ['Ferdi Kurnia Putra, S.P.W.K.', 'Product and RDI', null, 'Product Consultant Pratama', '1/2/2025', 'PKWT, CLTP', '1/3/2024', '1/6/2026', '19990110202505447', '1312011001990002', '62.948.690.3-202.000', 'S1', '10/1/1999', 'Laki-laki', '6281334462060', 'ferdi.kurnia10@gmail.com', 'Belum Menikah'],
            ['Firdausi Dhulhijjahyani, S.Pd., M.AP., M.Pd.', 'Product and RDI', null, 'Product Consultant Madya', '1/3/2020', 'PKWTT', '1/2/2025', null, '19930524202003026', '3514086405930002', null, 'S2', '24/5/1993', 'Perempuan', '6281233800045', 'firdausyhk@gmail.com', 'Belum Menikah'],
            ['Habil Maranda Maghfirullah, S.AP., M.AP.', 'Product and RDI', null, 'Product Consultant Madya', '1/3/2020', 'PKWTT', '1/2/2025', null, '19981214202003016', '3573051412980006', '63.520.014.0-652.000', 'S2', '19/3/1999', 'Laki-laki', '6281334696073', 'habilmaranda07@gmail.com', 'Belum Menikah'],
            ['I Wayan Satria Dharma Widhiantara, S.Tr.Kom.', 'Product and RDI', null, 'SaaS and AI Product Research Senior', '20/6/2021', 'PKWTT', '1/7/2023', '9/10/2026', '19980526202106075', '3507242605980001', '65.336.564.3-657.000', 'D-IV', '16/1/1990', 'Laki-laki', '6281358355497', 'dharmatara23@gmail.com', 'Belum Menikah'],
            ['Jihan Nuri Sifa Faradila, S.IP.', 'Product and RDI', null, 'Department Secretary', '5/10/2023', 'PKWT', '1/2/2025', '1/3/2026', '20000214202310299', '6271015402000002', null, 'S1', '14/2/2000', 'Perempuan', '6285156937263', 'jihannurisifafaradila@gmail.com', 'Menikah'],
            ['Khaisar Deri Saputra, S.AP.', 'Product and RDI', null, 'Administrative Officer Junior', '8/1/2024', 'PKWT', '1/7/2023', '2/9/2026', '19981206202401311', '6473020612980003', '99.959.277.7-652.000', 'S1', '6/12/1998', 'Laki-laki', '6282220006901', 'derikhaisarderi@gmail.com', 'Belum Menikah'],
            ['Khozainul Ilmi Faridullah, S.AP.', 'Product and RDI', 'Manager of Saas and AI', 'Product Consultant Muda', '1/3/2021', 'PKWTT', '1/7/2023', null, '19990911202103054', '3516181109990001', '99.073.090.5-602.000', 'S1', '11/9/1999', 'Laki-laki', '6285816764166', 'khozainul123@gmail.com', 'Menikah'],
            ['Lestari Eko Wahyudi, S.AP., M.AP.', 'Product and RDI', null, 'Product Consultant Madya', '1/3/2020', 'PKWTT', '1/2/2025', '1/10/2026', '19850310202001129', '3507291003850011', '44.459.330.5.654.000', 'S2', '10/3/1985', 'Laki-laki', '628134567692', null, 'Menikah'],
            ['M. Restu Akbar Ismail', 'Product and RDI', 'Manager of Online Course', null, '1/12/2022', 'PKWTT', '1/2/2025', null, '20010305202212232', '2171120503010001', '99.145.990.0-657.000', 'Menempuh S1', '5/3/2001', 'Laki-laki', '6282389581565', 'akbarrestu2001@gmail.com', 'Belum Menikah'],
            ['Mariska Dyah Astari, S.I.Kom.', 'Product and RDI', null, 'Production Specialist Junior', '1/4/2023', 'PKWT', '2/9/2025', '2/10/2026', '19990329202304254', '3503056903990002', '99.104.171.6-629.000', 'S1', '29/3/1999', 'Perempuan', '6285385563518', 'mariskadyahas@gmail.com', 'Belum Menikah'],
            ['Metriani', 'Product and RDI', null, 'Online Course Officer Junior', '1/11/2024', 'PKWT', '1/7/2023', '14/5/2026', '20040517202411410', '1604175705040001', '20.459.916.1-652.000', 'Menempuh S1', '17/5/2004', 'Perempuan', '6282269986424', 'metriani1745@gmail.com', 'Belum Menikah'],
            ['Nathania Valda Luchyta Sari, S.IP.', 'Product and RDI', null, 'Product Consultant Pratama', '4/12/2025', 'PKWT', '1/1/2024', '1/3/2026', '19990804202310293', '3504024408990002', null, 'S1', '4/8/1999', 'Perempuan', '6282230777568', 'nathvalda30@gmail.com', 'Belum Menikah'],
            ['Naufal Mahadika Driantama, S.E.', 'Product and RDI', null, 'Executive Education Officer Junior', '14/4/2025', 'PKWT', '14/4/2025', '1/2/2026', '20001211202504443', '3304061112000003', '20.044.056.8-652.000', 'S1', '11/12/2000', 'Laki-laki', '6281282022750', 'naufalmahadikadriantama@gmail.com', 'Belum Menikah'],
            ['Nidya Putri Syahida, S.AP., M.AP.', 'Product and RDI', null, 'Product Consultant Muda', '1/7/2023', 'PKWTT', '1/7/2023', null, '19900701202307283', '5201094107900424', '84.152.312.9-914.000', 'S2', '1/7/1990', 'Perempuan', '6281230715022', 'putrinidyasyahida@gmail.com', 'Menikah'],
            ['Novita Nur Hidayati, S.AP.', 'Product and RDI', 'Manager of Consulting Service', null, '1/3/2020', 'PKWTT', '1/8/2025', '1/12/2026', '19971105202003020', '3311044511970001', '50.483.435.9-652.000', 'S1', '5/11/1998', 'Perempuan', '6281216072500', 'novitanurhidayati16.nnh@gmail.com', 'Menikah'],
            ['Nurtya Laili Adnin, S.AB.', 'Product and RDI', null, 'Consultant Relation Officer Junior', '1/5/2025', 'PKWT', '1/2/2025', '1/10/2026', '19961016202505448', '3525165610960001', '92.487.782.2-612.000', 'S1', '16/10/1996', 'Perempuan', '6281232247323', 'nurtyalailiadnin23@gmail.com', 'Menikah'],
            ['Putri Benida Ayuningsih, S.AP., M.AP.', 'Product and RDI', 'Head of Product and RDI', null, '1/8/2025', 'PKWTT', null, null, '19981010202003036', '3507255010980001', '50.492.322.8-657.000', 'S2', '10/10/1998', 'Perempuan', '6289664099030', 'benidaputri@gmail.com', 'Belum Menikah'],
            ['Raga Bagas Pratama, S.PWK.', 'Product and RDI', null, 'Product Consultant Muda', '1/4/2023', 'PKWTT', '1/2/2025', '14/5/2026', '19990530202304271', '3578263005990003', '53.085.792.9-619.000', 'S1', '30/5/1999', 'Laki-laki', '628973808810', 'ragabagaspratama@gmail.com', 'Menikah'],
            ['Rica Oktaviana, S.Sos.', 'Product and RDI', null, 'Product Consultant Muda', '15/10/2020', 'PKWTT', '1/5/2024', null, '19981015202010046', '3523105510980001', '63.969.991.7-652.000', 'S1', '15/10/1998', 'Perempuan', '6288991131226', 'ricaoktaviana15@gmail.com', 'Menikah'],
            ['Shinta Fatimah Durrety, S.AP.', 'Product and RDI', 'Manager of Global Expansion', null, '8/8/2024', 'PKWT', '1/10/2025', '9/10/2026', '20010616202408393', '3572025606010002', '50.012.157.9-653.000', 'S1', '16/6/2001', 'Perempuan', '6281775229171', 'shintadurrety@gmail.com', 'Belum Menikah'],
            ['Sinta Muhimatul Ailiyah', 'Product and RDI', null, 'Product Consultant Pratama', '1/11/2024', 'PKWT', '1/2/2025', '1/3/2026', '20030408202411412', '3506134804030002', '20.436.579.5-655.000', 'Menempuh S1', '8/4/2003', 'Perempuan', '6285335293593', 'ailiyahsinta@gmail.com', 'Belum Menikah'],
            ['Syahra Ariesta Fitria Sari, S.AB., M.Si.', 'Product and RDI', null, 'Product Consultant Muda', '24/1/2022', 'PKWTT', '1/10/2025', null, '19960306202201100', '3504114603960002', '43.683.765.2-024.000', 'S2', '6/3/1996', 'Perempuan', '6281249834632', 'syahraariestafs@gmail.com', 'Belum Menikah'],
            ['Syahrul Mubarok, S.AP.', 'Product and RDI', null, 'Product Consultant Pratama', '1/12/2022', 'PKWTT', '1/10/2024', '2/10/2026', '19961112202212231', '3506061211960002', '60.139.553.6-655.000', 'S1', '12/11/1998', 'Laki-laki', '6285854192484', 'syahrulmubarok38@gmail.com', 'Belum Menikah'],
            ['Tasya Fiane Wardah, S.AP., M.AP.', 'Product and RDI', null, 'Production Specialist Senior', '1/1/2021', 'PKWTT', '1/10/2024', null, '19961217202103061', '3514175712960001', null, 'S2', '17/12/1998', 'Perempuan', '6285334324167', 'tfw.bekerja@gmail.com', 'Menikah'],
            ['Tiara Fany Permatasari, S.Sos.', 'Product and RDI', null, 'Executive Education Officer Junior', '16/6/2025', 'PKWT', '1/10/2025', '1/10/2026', '20011111202506461', '3579035111010001', '13.888.399.6-628.000', 'S1', '11/11/2001', 'Perempuan', '628987538383', 'tiarafany.11@gmail.com', 'Belum Menikah'],
            ['Tities Shofiana, S.IP.', 'Product and RDI', null, 'Consultant Relation Officer Senior', '1/4/2023', 'PKWTT', '1/12/2025', '2/2/2026', '20000815202304253', '3579015508000001', '50.486.773.0-628.000', 'S1', '15/8/2000', 'Perempuan', '6281249617306', 'titishofiana15@gmail.com', 'Belum Menikah'],
            ['Toriqul Mujahidi Ashshobrul Karim, S.AP.', 'Product and RDI', null, 'Product Consultant Muda', '1/11/2021', 'PKWTT', '1/1/2025', null, '20001108202110095', '3508030911000002', '39.279.328.7-625.000', 'S1', '9/10/2001', 'Laki-laki', '6285334324167', 'toriqul.mujahidi2015@gmail.com', 'Belum Menikah'],
            ['Ulvi Rohmatul Khasanah, S.AP.', 'Product and RDI', null, 'Online Course Officer Junior', '14/4/2025', 'PKWT', '1/8/2025', '14/5/2026', '20011009202504444', '3518114909010004', '07.603.432.1-655.000', 'S1', '9/10/2001', 'Perempuan', '6289520120106', 'ulvirk09@gmail.com', 'Belum Menikah'],
            ['Yuvan Kristiawan Suwandono, S.IP.', 'Product and RDI', null, 'Product Consultant Muda', '1/3/2021', 'PKWTT', '1/12/2025', '14/5/2026', '19980630202103055', '3578093006980003', '41.781.813.5-606.000', 'S1', '9/10/2001', 'Laki-laki', '6282238954290', 'yuvankristiawan@gmail.com', 'Belum Menikah'],
            ['Fachrozi Reza Herdian', 'Product and RDI', null, 'Product Consultant Pratama', '1/10/2024', 'PKWT, CLTP', '1/12/2025', '1/2/2026', '20000728202408426', '1672032807000001', '26.835.763.9-652.000', 'Menempuh S1', '28/7/2000', 'Laki-laki', '6282289188416', 'fachrozirezah@gmail.com', 'Belum Menikah'],
            ['Yumna Lutfiyyah', 'Product and RDI', null, 'Administrative Officer Junior', '1/8/2024', 'PKWT', '1/12/2024', '1/2/2026', '20030523202408427', '6171036305030002', null, 'Menempuh S1', '28/7/2000', 'Perempuan', '6289516278817', 'yumnaluthfiyyah2005@gmail.com', 'Belum Menikah'],
            ['Affidatul Azizah, S.AP.', 'Product and RDI', null, 'Product Consultant Pratama', '1/10/2024', 'PKWT', '1/12/2025', '1/12/2026', '20020120202408425', '3573016001020004', '21.035.653.1-652.000', 'S1', '20/1/2002', 'Perempuan', '6282244938779', 'affidazhf@gmail.com', 'Belum Menikah'],

            ['Adila Retno Pratiwi, S.E.', 'Revenue and Growth', 'Manager of Digital Revenue and Marketing', null, '1/8/2024', 'PKWTT', '1/12/2025', null, '19990306202106072', '3502094603990004', null, 'S1', '23/5/2003', 'Perempuan', '6285804788831', 'adilapratiwi99@gmail.com', 'Belum Menikah'],
            ['Ainun Ulumiyah, S.Pd.', 'Revenue and Growth', null, 'Digital Revenue and Marketing Officer Junior', '1/2/2025', 'PKWT', '1/12/2024', '2/9/2026', '19990319202502418', '3507245903990001', null, 'S1', '19/3/1999', 'Perempuan', '6289654585844', 'ulumiyahainun@gmail.com', 'Belum Menikah'],
            ['Andra Ridski Putri, S.Psi.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '1/2/2025', 'PKWT', '1/12/2025', '14/5/2026', '19900116202504433', '3573055601900001', '71.603.006.9-652.000', 'S1', '19/3/1999', 'Perempuan', '6285218144988', 'andrabayu060417@gmail.com', 'Menikah'],
            ['Anisa Putri, S.Pd.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '1/6/2024', 'PKWT', '1/12/2025', '1/5/2026', '19960423202103057', '3507036304960001', '61.463.696.7-652.000', 'S1', '16/1/1990', 'Perempuan', '6281259269823', 'anisa2338@gmail.com', 'Belum Menikah'],
            ['Anisah Durrotul Latifah, S.P.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '1/10/2024', 'PKWTT', '1/12/2024', null, '19940618202311302', '3505075806940001', '95.342.421.5-653.000', 'S1', '19/3/1999', 'Perempuan', '6281230082607', 'annisalatifa2@gmail.com', 'Menikah'],
            ['Arif Susianto, A.Md.Kom.', 'Revenue and Growth', null, 'Partnership Management Officer Ahli', '13/11/2023', 'PKWTT', '2/1/2026', '1/2/2026', '19860916202311303', '3573031609860004', '35.086.584.6-623.000', 'D-III', '16/1/1990', 'Laki-laki', '62811339113', 'ariefhonda86@gmail.com', 'Menikah'],
            ['Aries Lutfi Prasetyo, S.IP.', 'Revenue and Growth', null, 'Event and Program Specialist Senior', '1/3/2023', 'PKWTT', '1/4/2024', '1/8/2026', '19960408202303247', '3516010804960002', '39.204.005.1-602.000', 'S1', '8/4/1996', 'Laki-laki', '6285606631084', 'arieslutfi.prasetyo@gmail.com', 'Menikah'],
            ['Ashti Indah Permata Sari, S.I.Kom.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '10/6/2025', 'PKWT', '1/4/2025', '1/12/2026', '19970813202407370', '6302155308970002', '62.637.905.1-732.000', 'S1', '13/8/1997', 'Perempuan', '6281332647496', 'ashti.indah@gmail.com', 'Belum Menikah'],
            ['Belinda Tsana Dewi, S.Sos.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '4/6/2025', 'PKWT', '2/9/2025', '2/10/2026', '19981003202210206', '3172044310980005', '99.075.951.6-652.000', 'S1', '3/10/1998', 'Perempuan', '6281338370303', 'belindatsana03@gmail.com', 'Belum Menikah'],
            ['Bentarro Trisyahputra, S.AB., M.AB.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '24/1/2022', 'PKWTT', '1/12/2023', '1/2/2026', '19971123202201125', '1375022311970002', '99.968.964.9-202.000', 'S2', '23/11/1997', 'Laki-laki', '6282170323615', 'bentarrotri@student.ub.ac.id', 'Menikah'],
            ['Candra Bimantara', 'Revenue and Growth', null, 'Graphic Designer Junior', '24/1/2022', 'PKWTT', '2/1/2026', '2/10/2026', '19930204202201119', '3514180402930001', '39.319.214.1-624.000', 'SMK', '4/2/1993', 'Laki-laki', '6282244158877', 'candralenwork@gmail.com', 'Menikah'],
            ['Chausar Yahya Ramadhanto, S.E.', 'Revenue and Growth', null, 'Partnership Management Officer Ahli', '1/7/2025', 'PKWTT', '1/7/2023', '1/2/2026', '19980121202111097', '3311062101980001', null, 'S1', '21/1/1998', 'Laki-laki', '62895364776907', 'chausaryr@gmail.com', 'Menikah'],
            ['Cornelius Padu Baringin Simanullang, S.AP.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '15/8/2023', 'PKWTT', '2/1/2026', null, '20000819202308291', '3507221908000002', '40.021.010.0-657.000', 'S1', '19/8/2000', 'Laki-laki', '6289687046786', 'corneliuspadub@gmail.com', 'Belum Menikah'],
            ['Destrina Elita Koto, S.AB.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '10/7/2023', 'PKWT', '1/10/2024', '2/2/2026', '19981201202307281', '1273044112980004', '53.991.885.4-126.000', 'S1', '1/12/1998', 'Perempuan', '6282244109803', 'elitadestrina@gmail.com', 'Belum Menikah'],
            ['Ega Rezauddin, A.Md.', 'Revenue and Growth', null, 'Performance Marketing Specialist Senior', '4/12/2025', 'PKWTT', '2/1/2026', null, '19830718202512483', '3204051807830003', null, null, '18/7/1983', 'Laki-laki', '6281222228307', 'eggs83@gmail.com', 'Menikah'],
            ['Ekky Purnama Sari, S.AP.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '4/6/2025', 'PKWTT', '1/11/2025', '2/2/2026', '19990628202407367', '3518086806990001', '63.552.022.4-652.000', 'S1', '28/6/1999', 'Perempuan', '6285859137636', 'purnamasariekky@gmail.com', 'Belum Menikah'],
            ['Erdin Rusandy Sahfitrah, S.Ant.', 'Revenue and Growth', null, 'Graphic Designer Junior', '4/6/2025', 'PKWTT', '1/12/2025', '2/10/2026', '20000115202211218', '3578101501000005', '61.694.488.0-619.000', 'S1', '15/1/2000', 'Laki-laki', '6288217188358', 'erdinrusandy15@gmail.com', 'Belum Menikah'],
            ['Fajrian Alviano, S.Sn.', 'Revenue and Growth', null, 'Graphic Designer Senior', '11/10/2021', 'PKWTT', '1/2/2024', null, '19980419202110084', '3216011904980001', '65.324.863.3-435.000', 'S1', '19/4/1998', 'Laki-laki', '6281238036201', 'alvinalviano74@gmail.com', 'Menikah'],
            ['Fariza Maher Fauzy, S.AP.', 'Revenue and Growth', null, 'Graphic Designer Senior', '1/3/2020', 'PKWTT', '1/10/2024', null, '19970515202003037', '3505011505970003', null, 'S1', '15/5/1997', 'Laki-laki', '6281554240422', 'ffariza17@gmail.com', 'Belum Menikah'],
            ['Friance Halena Ndun, S.Pd.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '1/8/2025', 'Bond Agreement', '1/2/2024', '1/2/2026', '19910921202508472', '5371036109910003', '66.096.386.9-992.000', 'S1', '21/9/1991', 'Perempuan', '6281296199614', 'ndunfriance@gmail.com', 'Menikah'],
            ['Fuad Al Fajrin Bafadal, S.T.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '8/5/2024', 'PKWT', '1/10/2024', '1/12/2026', '19950625202405341', '7403161307970002', '85.154.989.9-819.000', 'S1', '25/6/1995', 'Laki-laki', '6285299251025', 'fuadbafadal87@gmail.com', 'Menikah'],
            ['Khrisna Ambar Ramdhani, S.I.Kom.', 'Revenue and Growth', null, 'Content Creator Senior', '1/10/2024', 'PKWTT', '1/2/2024', '1/12/2026', '19981226202207196', '3507186812980001', '99.128.839.0-657.000', 'S1', '26/12/1998', 'Perempuan', '6283847722435', 'arakhrisna8@gmail.com', 'Menikah'],
            ['Lianawati, S.I.Kom.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '14/2/2022', 'PKWTT', '1/12/2025', null, '19900117202202190', '3573045701900004', '82.678.385.4-623.000', 'S1', '18/1/1990', 'Perempuan', '6283848504970', 'liaacutex@gmail.com', 'Belum Menikah'],
            ['Ludy Osama Al Rasyid, S.M.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '10/9/2021', 'PKWTT', '1/3/2025', '4/7/2026', '20010920202109078', '3573052009010003', null, 'S1', '20/9/2001', 'Laki-laki', '6285749580300', 'ludyosama20@gmail.com', 'Belum Menikah'],
            ['M. Amirul Mu\'minin, S.IP.', 'Revenue and Growth', null, 'Copywriter Senior', '1/4/2021', 'PKWTT', '1/12/2025', null, '19950720202204199', '3518102007950005', '39.204.059.8-655.000', 'S1', '20/7/1995', 'Laki-laki', '6285735805552', 'amirulmu2007@gmail.com', 'Belum Menikah'],
            ['Maria Sekar Nugraheni Cahyaningtyas, S.AP.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '1/4/2024', 'PKWT', '2/8/2025', '1/8/2026', '20020611202404322', '3273215106020001', '96.251.654.8-424.000', 'S1', '11/6/2002', 'Perempuan', '6281214109756', 'msnc1106@gmail.com', 'Belum Menikah'],
            ['Melania Galih Safitri, S.M.', 'Revenue and Growth', null, 'Administrative Officer Junior', '3/10/2022', 'PKWTT', '1/8/2024', null, '20000126202210212', '3579016601000001', '99.093.750.0-628.000', 'S1', '26/1/2000', 'Perempuan', '6281553466790', 'mellaniagalih26@gmail.com', 'Belum Menikah'],
            ['Mochammad Ubaidillah, S.AP.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '1/3/2020', 'PKWTT', '1/12/2025', null, '19981031202003033', '3515103110980002', '50.546.853.8-603.000', 'S1', '20/7/1995', 'Laki-laki', '6285546075727', 'ubproduction3@gmail.com', 'Belum Menikah'],
            ['Mohamad Afton Hilman, S.AP.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '20/3/2024', 'PKWT', '1/3/2025', '1/8/2026', '19990216202403318', '3507201602990001', '82.411.095.1-654.000', 'S1', '16/2/1999', 'Laki-laki', '6289657255410', 'aftonhilman16@gmail.com', 'Belum Menikah'],
            ['Muhamad Ariz Pratama, S.AB.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '1/4/2023', 'PKWTT', '1/12/2025', null, '19981216202304255', '3603281612980005', '99.179.793.7-657.000', 'S1', '11/6/2002', 'Laki-laki', '6282234551940', 'arizpratama02@gmail.com', 'Menikah'],
            ['Much Rifki Dwi Afiantara, S.I.Kom.', 'Revenue and Growth', null, 'Copywriter Junior', '1/11/2022', 'PKWTT', '1/2/2024', '2/2/2026', '19981107202211221', '3573040711980003', '39.160.664.7.623.000', 'S1', '7/11/1998', 'Laki-laki', '6287772252565', 'rifki.afiantara@gmail.com', 'Belum Menikah'],
            ['Mohammad Ivan Haris Kurniawan, A.Md.', 'Revenue and Growth', 'Coordinator of Digital Creative', 'Videographer and Editor Senior', '15/10/2020', 'PKWTT', '1/12/2025', '1/12/2026', '19980505202010044', '3573030505980001', '39.203.974.9-623.000', 'D-III', '5/5/1998', 'Laki-laki', '6287848705507', 'smartid.ivan@gmail.com', 'Menikah'],
            ['Mukhamad Yusuf, S.Pd.', 'Revenue and Growth', null, 'Performance Marketing Specialist Senior', '2/9/2024', 'PKWTT', '1/3/2025', null, '19950909202409398', '3507250909950001', '61.398.248.7-657.000', 'S1', '9/9/1995', 'Laki-laki', '6281907215411', 'm.yosh691@gmail.com', 'Menikah'],
            ['Muliawan Adha Heldifa, S.E.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '8/7/2024', 'PKWT', '10/6/2025', '1/2/2026', '19950510202407360', '1302101005950003', '94.692.101.2-203.000', 'S1', '10/5/1995', 'Laki-laki', '6282134489439', 'adhaheldifa@gmail.com', 'Belum Menikah'],
            ['Muthi Aisyah, A.Md.', 'Revenue and Growth', null, 'Videographer and Editor Senior', '15/10/2020', 'PKWTT', '1/8/2022', null, '19970215202010043', '3573017110970005', '39.167.733.3-652.000', 'D-III', '31/10/1997', 'Perempuan', '6283856362577', 'muthiaisyah31@gmail.com', 'Menikah'],
            ['Oktavia Helda Theresia Wonsiwor, ST.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '1/11/2025', 'Bond Agreement', '1/11/2025', '1/2/2026', '19851031202511482', '9171037110850007', '43.840.215.8-953.000', 'S1', '31/10/1985', 'Perempuan', '6281244098562', 'oktaviahelda916@gmail.com', 'Menikah'],
            ['Pandu Dwi Cahyo, S.AP.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '15/8/2023', 'PKWTT', '2/1/2026', '1/5/2026', '19940505202308287', '3517090505940007', '80.531.442.4-602.000', 'S1', '5/5/1994', 'Laki-laki', '6285259616563', 'pandudwic@gmail.com', 'Menikah'],
            ['Rahmat Hidayat, S.IP.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '17/11/2025', 'Internship', '1/1/2025', '17/3/2026', '20000604202511481', '6472010406000004', null, 'S1', '4/6/2000', 'Laki-laki', '6281235567918', 'rahmathdyt4620@gmail.com', 'Belum Menikah'],
            ['Restu Mulya Pratama, S.AB., M.AB.', 'Revenue and Growth', 'Vice President of Revenue and Growth', null, '1/3/2020', 'PKWTT', '1/2/2025', null, '19981022202003032', '1809042210980002', '96.385.731.3-623.000', 'S2', '22/10/1998', 'Laki-laki', '6282232305995', 'restumulyapratama@gmail.com', 'Menikah'],
            ['Siti Fadilah Nur Alminia, S.AP.', 'Revenue and Growth', null, 'Administrative Officer Junior', '3/10/2022', 'PKWTT', '1/10/2024', '1/8/2026', '20000213202210215', '3573045302000005', '99.063.818.1-623.000', 'S1', '13/2/2000', 'Perempuan', '6289692387187', 'fadilahnia13@gmail.com', 'Belum Menikah'],
            ['Wa Ode Rizky Fitria, S.H.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '1/6/2024', 'PKWTT', '4/6/2025', null, '19890416202406347', '9271025604890005', '46.895.967.1-951.000', 'S1', '16/4/1989', 'Perempuan', '6282198045969', 'kikirizky301@gmail.com', 'Menikah'],
            ['Yahya Nur Fahmi Fauzi, S.Tr.I.Kom.', 'Revenue and Growth', null, 'Graphic Designer Junior', '1/10/2024', 'PKWT', '4/6/2025', '1/2/2026', '19990212202410408', '3314081202990001', '50.002.742.0-528.000', 'D-IV', '12/2/1999', 'Laki-laki', '6285726634489', 'yahyanurff88@gmail.com', 'Belum Menikah'],
            ['Yeni Puspita, S.P.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '15/1/2024', 'PKWTT', '1/2/2025', null, '19970604202401314', '3515134406970010', '93.269.019.1-603.000', 'S1', '4/6/1997', 'Perempuan', '6281332335708', 'yenijeni4@gmail.com', 'Belum Menikah'],
            ['Yoga Muhammad Yusuf, S.I.Kom.', 'Revenue and Growth', null, 'Partnership Management Officer Pratama', '13/11/2023', 'PKWTT', '1/7/2025', null, '19970713202311305', '3672021307970003', '43.490.431.4-035.000', 'S1', '13/7/1997', 'Laki-laki', '6282228729669', 'yogamuhammadyusuf1997@gmail.com', 'Belum Menikah'],

            ['Akhmad Faizal, S.Kom.', 'Technology', null, 'AI Engineer Senior', '10/6/2025', 'PKWT', '10/6/2025', '1/2/2026', '20000323202506464', '1101082303000005', '62.301.449.5-542.000', 'S1', '23/3/2000', 'Laki-laki', '6281188060301', 'akhmadfaizal.works@gmail.com', 'Belum Menikah'],
            ['Attar Fikri Diwanto Hariseno, S.Kom.', 'Technology', null, 'Programmer Junior', '4/6/2025', 'PKWT', '1/1/2025', '4/7/2026', '19991019202506456', '1271031910990007', null, 'S1', '19/10/1999', 'Laki-laki', '6281380172274', 'attar.fikri30@gmail.com', 'Belum Menikah'],
            ['Bambi Adama Janaloka Basundara, S.AP.', 'Technology', null, 'Project Manager Junior', '5/10/2023', 'PKWT', '10/10/2025', '1/2/2026', '19970727202310300', '3507222707970002', '99.128.919.0-657.000', 'S1', '27/7/1997', 'Laki-laki', '6285850102248', 'bambiadama@gmail.com', 'Belum Menikah'],
            ['Dr. Kasyful Amron, S.T., M.Sc.', 'Technology', null, 'Advisor Pengembangan', '1/7/2025', 'PKWT', '4/6/2025', '1/8/2026', '19750803202507469', '3573050308750011', '66.456.536.3-652.000', 'S2', '3/8/1975', 'Laki-laki', '628123543225', 'kasyful@ub.ac.id', 'Menikah'],
            ['Dwy Saputro Nugroho, S.Kom.', 'Technology', null, 'Programmer Senior', '1/3/2020', 'PKWTT', '1/9/2024', null, '19920213202003022', '3525141302920002', null, 'S1', '13/2/1992', 'Laki-laki', '6285707274719', 'spiritsoul31@gmail.com', 'Menikah'],
            ['Ivan Yulfrian, S.Kom.', 'Technology', null, 'Development and Operations Engineer Senior', '1/3/2020', 'PKWTT', '1/2/2024', null, '19890318202003024', '3573031803890001', '76.323.301.2-623.000', 'S1', '18/3/1989', 'Laki-laki', '628980077050', 'ivan.yulfrian@ub.ac.id', 'Menikah'],
            ['Nabila Indah Rahmadani Choirunisa, S.AP.', 'Technology', null, 'Project Manager Junior', '1/7/2022', 'PKWT', '1/10/2024', '10/7/2026', '19991211202207197', '3203015112990001', null, 'S1', '11/12/1999', 'Perempuan', '6281219594225', 'nabilairch@outlook.com', 'Belum Menikah'],
            ['Nisma Fadillah Abdul Rahman Onge', 'Technology', null, 'AI Engineer Junior', '4/6/2025', 'PKWT', '4/6/2025', '4/7/2026', '20040425202506458', '3276026504040007', null, 'Menempuh S1', '25/4/2004', 'Perempuan', '6282123055341', 'nisma.aro@gmail.com', 'Belum Menikah'],
            ['Noval Luqmana Annurussyah, S.Kom.', 'Technology', null, 'Programmer Senior', '4/6/2025', 'PKWT', '4/6/2025', '1/3/2026', '19941125202506459', '3573012511940005', '50.814.206.4-652.000', 'S1', '25/11/1994', 'Laki-laki', '6282257111684', 'puremachine99@gmail.com', 'Menikah'],
            ['Thusti Dwi Purnami, A.Md.', 'Technology', null, 'System Analyst Senior', '1/3/2020', 'PKWTT', '1/9/2024', '1/8/2026', '19790125202003025', '3573056501790003', '57.593.974.9-652.000', 'D-III', '25/1/1979', 'Perempuan', '6281333002079', 'tustie.dwi@gmail.com', 'Menikah'],
            ['Miatu Khabbah Suria, S.AP.', 'Technology', null, 'User Interface/Experience Designer Junior', '15/12/2023', 'PKWT', '2/12/2025', '15/4/2026', '20000406202312421', '3514114604000003', '99.495.551.6-624.000', 'S1', '6/4/2000', 'Perempuan', '6285791124091', 'miatumia41@gmail.com', 'Belum Menikah'],
            ['Suci Navasari, S.I.P.', 'Technology', null, 'Project Manager Junior', '15/12/2023', 'PKWT', '1/1/2025', '15/4/2026', '20010702202312422', '3507274207010001', '99.561.808.9-657.000', 'S1', '2/7/2001', 'Perempuan', '6281231397772', 'navasaoffice@gmail.com', 'Belum Menikah'],
            ['Itsar Irsyada Surga, S.T.', 'Technology', 'Manager of Technology', 'Programmer Senior', '1/8/2024', 'PKWTT', '10/10/2025', null, '20020621202408423', '3311102106020004', '12.753.948.4-532.000', 'S1', '21/6/2002', 'Laki-laki', '6282137271295', 'Itsar.Irsyada10@gmail.com', 'Belum Menikah'],
            ['Aliyah Nafilah, S.AB.', 'Technology', null, 'Quality Assurance Specialist Junior', '1/10/2024', 'PKWT', '1/1/2025', '4/7/2026', '19960719202410428', '1671045907960005', '42.856.068.4-307.000', 'S1', '19/7/1996', 'Perempuan', '628983091681', 'aliyahnafilah19@gmail.com', 'Belum Menikah'],
            ['Wisnu Fajar Dewantara, S.Kom.', 'Technology', null, 'Programmer Senior', '9/9/2024', 'PKWT', '10/10/2025', '4/7/2026', '19940502202409429', '6474030205940006', '40.811.724.0-657.000', 'S1', '2/5/1994', 'Laki-laki', '6285158447674', 'wisnupriester@gmail.com', 'Belum Menikah'],
        ];

        foreach ($rows as $row) {
            [
                $name, $dept, $jabStruktural, $jabFungsional,
                $joinDate, $statusRaw,
                $contractStart, $contractEnd,
                $nis, $nik, $npwp, $edu, $birthDate, $gender,
                $phone, $email, $marital
            ] = $row;

            [$empStatus, $isCltp] = $parseStatus($statusRaw ?? '');

            $cleanEmail = strtolower(trim(explode('/', $email ?? '')[0]));
            if (! $cleanEmail) {

                $cleanEmail = Str::slug($name).'@placeholder.invalid';
            }

            if (DB::table('users')->where('email', $cleanEmail)->exists()) {
                continue;
            }

            $cleanNik = $nik ?: null;
            $cleanNpwp = $npwp ?: null;

            if ($cleanNik && DB::table('employees')->where('nik', $cleanNik)->exists()) {
                $cleanNik = null;
            }
            if ($cleanNpwp && DB::table('employees')->where('npwp', $cleanNpwp)->exists()) {
                $cleanNpwp = null;
            }

            $userId = DB::table('users')->insertGetId([
                'name' => $name,
                'email' => $cleanEmail,
                'password' => bcrypt('qQ123!@#'),
                'email_verified_at' => now(),
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('employees')->insert([
                'id' => (string) Str::uuid(),
                'user_id' => $userId,
                'department_id' => $dept ? $deptId($dept) : null,
                'position_struktural_id' => $jabStruktural ? $posId($jabStruktural) : null,
                'position_fungsional_id' => $jabFungsional ? $posId($jabFungsional) : null,
                'employment_status' => $empStatus,
                'is_cltp' => $isCltp,
                'contract_start_date' => $parseDate($contractStart),
                'contract_end_date' => $parseDate($contractEnd),
                'nis' => $nis ?: null,
                'nik' => $cleanNik,
                'npwp' => $cleanNpwp,
                'education_level' => $edu ?: null,
                'birth_date' => $parseDate($birthDate),
                'gender' => $gender ?: null,
                'phone_number' => $phone ?: null,
                'marital_status' => $parseMarital($marital ?? ''),
                'target_okr' => null,
                'pipeline_permissions' => null,
                'kpi_achieved_m1' => 0.00,
                'kpi_achieved_m2' => 0.00,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
