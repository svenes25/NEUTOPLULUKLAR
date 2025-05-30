<?php

// app/Http/Controllers/ToplulukController.php

namespace App\Http\Controllers;

use App\Models\OgrenciBilgi;
use App\Models\Topluluk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Uye;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class ToplulukController extends Controller
{
    public function show($topluluk_isim, $topluluk_id)
    {
        $topluluk = Topluluk::find($topluluk_id);
        if (!$topluluk) {
            return abort(404, 'Topluluk bulunamadı');
        }

        return view('tplk_anasayfa', ['topluluk' => $topluluk]);
    }
    public function uyeIslemleri($isim, $id)
    {
        $topluluk = Topluluk::find($id);

        if (!$topluluk) {
            abort(404, 'Topluluk bulunamadı.');
        }

        return view('tplk_uyeislemleri', compact('topluluk'));
    }
    public function kayitol(Request $request)
    {
        $response = $request->input('g-recaptcha-response');
        $secretKey = "6LcFD6YpAAAAAA8rNdPgqJMQvPfTY7GqSnFS4voH";
        $url = "https://www.google.com/recaptcha/api/siteverify?secret=" . $secretKey . "&response=" . $response;
        $recaptchaResponse = Http::asForm()->post($url);
        $recaptcha = $recaptchaResponse->json();
        if ($recaptcha["success"]) {
        $curl = curl_init();
        $tc = $request->input('tc');
        $sifre = $request->input('sifre');
        $jsonVerisi = [
            "Tip" => "ogrenci",
            "TcKn" => $tc,
            "Sifre" => $sifre,
        ];
        //CURL SETUPLAYIN
        $response = curl_exec($curl);
        curl_close($curl);
        $data = json_decode($response, true);
        if($data["Durum"]) {
            $curl = curl_init();
            //CURL SETUPLAYIN
            $response = curl_exec($curl);
            curl_close($curl);
            $data = json_decode($response, true);
            $yeniTarih = \Carbon\Carbon::createFromFormat('d.m.Y', $data[0]["KAY_TAR"])->format('Y-m-d');
            $tc = $data[0]["TCK"];
            $ogrenci = DB::table('ogrenci_bilgi')->where('tc', $tc)->first();
            if(!$ogrenci) {
                $id = DB::table('ogrenci_bilgi')->insertGetId([
                    'numara' => $data[0]["OGR_NO"],
                    'tc' => $data[0]["TCK"],
                    'isim' => $data[0]["AD"],
                    'soyisim' => $data[0]["SOYAD"],
                    'fak_ad' => $data[0]["FAK_AD"],
                    'bol_ad' => $data[0]["BOL_AD"],
                    'prog_ad' => $data[0]["PROG_AD"],
                    'sınıf' => $data[0]["SINIF"],
                    'kay_tar' => $yeniTarih,
                    'ogrenim_durum' => $data[0]["OGRENIM_DURUM"],
                    'ogrenim_tip' => $data[0]["OGRENIM_TIP"],
                    'ayr_tar' => $data[0]["AYR_TAR"],
                    'tel' => $data[0]["TELEFON"],
                    'tel2' => $data[0]["TELEFON2"],
                    'eposta' => $data[0]["EPOSTA1"],
                    'eposta2' => $data[0]["EPOSTA2"],
                    'program_tip' => $data[0]["PROGRAM_TIP"],
                    'durum' => $data[0]["DURUM"]
                ]);
            }
            else{
                $id=$ogrenci->id;
            }
            $top=$request->topluluk;
            $uye = DB::table('uyeler')
                ->where('ogr_id', $id)
                ->where('top_id', $top)
                ->first();
            if ($uye) {
                return redirect()->back()->with('danger', 'Zaten Bu Topluluğa Üyesiniz!');
            }
            else {
                $membershipForm = $request->file('membership_form');
                $tarih = now()->format('Y-m-d_H-i-s');
                $dosyaAdi = $tc . '_' . $tarih . '.' . $membershipForm->getClientOriginalExtension();
                $uye = new Uye();
                $uye->ogr_id = $id;
                $uye->top_id = $top;
                $uye->tarih = $tarih;
                $uye->belge = $dosyaAdi;
                $uye->save();
                $membershipForm->move(public_path('docs/kayit_belge'), $dosyaAdi);
                return redirect()->back()->with('success', 'Kayıt Başarıyla Tamamlandı!');
            }
        }
        else
        {
            return redirect()->back()->with('danger', 'Üniversiteye Kaydınız Bulunamadı!');
        }
        }
        else
        {
            return redirect()->back()->with('danger', 'Lütfen Doğrulamayı Yapın!');
        }
    }
    public function index()
    {
        $topluluklar = Topluluk::all();
        return view('denetim_uye', compact('topluluklar'));
    }
    public function indextopluluk()
    {
        $perPage = 9;
        $currentPage = request()->query('page', 1);
        $offset = ($currentPage - 1) * $perPage;
        $topluluklar = DB::table('topluluklar')
            ->where('durum','=','1')
            ->skip($offset)
            ->take($perPage)
            ->get();
        $totalForms = DB::table('topluluklar')
            ->where('durum','=','1')
            ->count();
        $lastPage = ceil($totalForms / $perPage);
        $pperPage = 9;
        $pcurrentPage = request()->query('sayfa', 1);
        $poffset = ($pcurrentPage - 1) * $pperPage;
        $ptopluluklar = DB::table('topluluklar')
            ->where('durum','=','2')
            ->skip($poffset)
            ->take($pperPage)
            ->get();
        $ptotalForms = DB::table('topluluklar')
            ->where('durum','=','2')
            ->count();
        $plastPage = ceil($ptotalForms / $perPage);
        $pcurrentPage = request()->query('sayfa', 1);
        return view('denetim_topluluk', compact('topluluklar', 'currentPage', 'lastPage', 'perPage', 'totalForms','ptopluluklar', 'ptotalForms', 'plastPage', 'pcurrentPage'));
    }
    public function uyeListesi($id)
    {
        $topluluklar = Topluluk::all();
        $uyeler = DB::table('uyeler')
            ->join('ogrenci_bilgi', 'uyeler.ogr_id', '=', 'ogrenci_bilgi.id')
            ->where('uyeler.top_id', $id)
            ->where('uyeler.durum','=',1)
            ->select(
                'uyeler.id as id',
                'uyeler.tarih as tarih',
                'ogrenci_bilgi.numara as numara',
                'ogrenci_bilgi.isim as isim',
                'ogrenci_bilgi.soyisim as soyisim',
                'ogrenci_bilgi.fak_ad as fak_ad',
                'ogrenci_bilgi.bol_ad as bol_ad',
                'ogrenci_bilgi.tel as tel',
                'uyeler.belge as belge',
                'uyeler.rol as rol'
            )
            ->get();
        return response()->json($uyeler);
    }
    public function basvuruListesi($id)
    {
        $topluluklar = Topluluk::all();
        $uyeler = DB::table('uyeler')
            ->join('ogrenci_bilgi', 'uyeler.ogr_id', '=', 'ogrenci_bilgi.id')
            ->where('uyeler.top_id', $id)
            ->where('uyeler.durum', '=', 0)
            ->select(
                'ogrenci_bilgi.id as id',
                'uyeler.tarih as tarih', //
                'ogrenci_bilgi.numara as numara',
                'ogrenci_bilgi.isim as isim',
                'ogrenci_bilgi.soyisim as soyisim',
                'ogrenci_bilgi.fak_ad as fak_ad',
                'ogrenci_bilgi.bol_ad as bol_ad',
                'ogrenci_bilgi.tel as tel',
                'uyeler.belge as belge'
            )
            ->get();
        return response()->json($uyeler);
    }

    public function updateApplicationStatus(Request $request)
    {
        $id = $request->input('id');
        $durum = $request->input('durum');
        $t_id = $request->input('t_id');
        $affected = DB::table('uyeler')
            ->where('ogr_id', $id)
            ->where('top_id', $t_id)
            ->update(['durum' => $durum]);
        if ($affected) {
            return response()->json(['success' => true, 'message' => 'Başvuru durumu güncellendi.']);
        } else {
            return response()->json(['success' => false, 'message' => 'Güncelleme başarısız oldu.']);
        }
    }
    public function updateRol(Request $request)
    {
        $id = $request->input('id');
        $durum = $request->input('rol');
        $affected = DB::table('uyeler')
            ->where('id', $id)
            ->update(['rol' => $durum]);
        if ($affected) {
            return response()->json(['success' => true, 'message' => 'Rol durumu güncellendi.']);
        } else {
            return response()->json(['success' => false, 'message' => 'Güncelleme başarısız oldu.']);
        }
    }
    public function yeniUyeEkle(Request $request)
    {
        $tc = $request->post('ogrno');
        $toplulukId = $request->input('topluluk_id');
        $belge = $request->file('belge');
        $ogrenci    = OgrenciBilgi::where('tc', $tc);
        if ($ogrenci->count() > 0) {
            $ogrenci_detay = $ogrenci->first();
            $uyeVarMi   = Uye::where('ogr_id', $ogrenci_detay["id"])
                ->where('top_id', $toplulukId);
            if ($uyeVarMi->count() > 0) {
                return response()->json(['success' => true, 'message' => 'Üye Kaydedildi güncellendi.']);
            }
            else {
                $tcKimlik = $ogrenci_detay["tc"];
                $timestamp = now()->format('Ymd_His');
                $gun = now()->format('Ymd');
                $fileName = $tcKimlik.'_'.$timestamp.'.'.$belge->getClientOriginalExtension();
                $belge->move(public_path('docs/kayit_belge'), $fileName);
                $uyeClass = new Uye();
                $uyeClass->ogr_id   = $ogrenci_detay["id"];
                $uyeClass->top_id   = $toplulukId;
                $uyeClass->belge   = $fileName;
                $uyeClass->tarih   = $gun;
                $uyeClass->rol   = 1;
                $save_uye   = $uyeClass->save();
                if ($save_uye) {
                    return response()->json(['success' => true, 'message' => 'Üye kaydedildi.']);
                } else {
                    return response()->json(['danger' => true, 'message' => 'Üye kaydedilemedi.']);
                }

            }
        }
        else
        {
            $curl = curl_init();
            //CURL SETUPLAYIN
            $response = curl_exec($curl);
            curl_close($curl);
            $data = json_decode($response, true);
            $yeniTarih = \Carbon\Carbon::createFromFormat('d.m.Y', $data[0]["KAY_TAR"])->format('Y-m-d');
            $id = DB::table('ogrenci_bilgi')->insertGetId([
                    'numara' => $data[0]["OGR_NO"],
                    'tc' => $data[0]["TCK"],
                    'isim' => $data[0]["AD"],
                    'soyisim' => $data[0]["SOYAD"],
                    'fak_ad' => $data[0]["FAK_AD"],
                    'bol_ad' => $data[0]["BOL_AD"],
                    'prog_ad' => $data[0]["PROG_AD"],
                    'sınıf' => $data[0]["SINIF"],
                    'kay_tar' => $yeniTarih,
                    'ogrenim_durum' => $data[0]["OGRENIM_DURUM"],
                    'ogrenim_tip' => $data[0]["OGRENIM_TIP"],
                    'ayr_tar' => $data[0]["AYR_TAR"],
                    'tel' => $data[0]["TELEFON"],
                    'tel2' => $data[0]["TELEFON2"],
                    'eposta' => $data[0]["EPOSTA1"],
                    'eposta2' => $data[0]["EPOSTA2"],
                    'program_tip' => $data[0]["PROGRAM_TIP"],
                    'durum' => $data[0]["DURUM"]
                ]);
            if($id)
            {
                $timestamp = now()->format('Ymd_His');
                $gun = now()->format('Ymd');
                $fileName = $tc.'_'.$timestamp.'.'.$belge->getClientOriginalExtension();
                $belge->move(public_path('docs/kayit_belge'), $fileName);
                $uyeClass = new Uye();
                $uyeClass->ogr_id   = $id;
                $uyeClass->top_id   = $toplulukId;
                $uyeClass->belge   = $fileName;
                $uyeClass->tarih   = $gun;
                $uyeClass->rol   = 1;
                $save_uye   = $uyeClass->save();
                if ($save_uye) {
                    return response()->json(['success' => true, 'message' => 'Üye kaydedildi.']);
                } else {
                    return response()->json(['danger' => true, 'message' => 'Üye kaydedilemedi.']);
                }
            }

        }
    }
    public function getSilinecekUyeler($toplulukId)
    {
        $uyeler = DB::table('uyeler')
            ->join('ogrenci_bilgi', 'uyeler.ogr_id', '=', 'ogrenci_bilgi.id')
            ->select(
                'uyeler.id',
                'uyeler.tarih as tarih',
                'ogrenci_bilgi.numara as numara',
                'ogrenci_bilgi.isim as isim',
                'ogrenci_bilgi.soyisim as soyisim',
                'ogrenci_bilgi.tel',
                'ogrenci_bilgi.fak_ad as fakulte',
                'ogrenci_bilgi.bol_ad as bolum',
                'uyeler.belge'
            )
            ->where('uyeler.top_id', $toplulukId)
            ->where('uyeler.durum', 1)
            ->get();

        return response()->json($uyeler);
    }
    public function deleteUye(Request $request)
    {
        $uyeId = $request->input('id');
        $yeniDurum = $request->input('durum');
        Log::info('Gelen Request:', $request->all());
        $uye = DB::table('uyeler')->where('id', $uyeId)->first();
        if (!$uye) {
            return response()->json([
                'success' => false,
                'message' => 'Üye bulunamadı.'
            ], 404);
        }
        DB::table('uyeler')->where('id', $uyeId)->update(['durum' => $yeniDurum]);

        return response()->json([
            'success' => true,
            'message' => 'Üyenin durumu başarıyla güncellendi.'
        ], 200);
    }
    public function Iletisim(Request $request)
    {
        $response = $request->input('g-recaptcha-response');
        $secretKey = "6LcFD6YpAAAAAA8rNdPgqJMQvPfTY7GqSnFS4voH";
        $url = "https://www.google.com/recaptcha/api/siteverify?secret=" . $secretKey . "&response=" . $response;
        $recaptchaResponse = Http::asForm()->post($url);
        $recaptcha = $recaptchaResponse->json();
        if ($recaptcha["success"]) {
            $id = $request->input('id');
            $isim = $request->input('name');
            $email = $request->input('email');
            $mesaj = $request->input('message');

            $yonetici = DB::table('uyeler')
                ->join('ogrenci_bilgi', 'uyeler.ogr_id', '=', 'ogrenci_bilgi.id')
                ->where('top_id', '=', $id)
                ->where('rol', '=', 2)
                ->where('uyeler.durum', '=', 1)
                ->pluck('eposta');
            $emailAdresleri = $yonetici[0];
            if (empty($emailAdresleri)) {
                return back()->with('error', 'Topluluk yöneticileri bulunamadı veya e-posta adresi eksik.');
            }
            $emailData = [
                'isim' => $isim,
                'email' => $email,
                'mesaj' => $mesaj
            ];

            Mail::send('email', $emailData, function ($message) use ($emailAdresleri) {
                $message->to($emailAdresleri)
                    ->subject("Yeni Geri Bildirim");
            });

            return back()->with('success', 'Geri bildiriminiz başarıyla gönderildi.');
        }
        else{
            return back()->with('danger', 'Lütfen Doğrulamayı yapın.');
        }
    }
    public function form()
    {
        $perPage = 10;
        $currentPage = request()->query('page', 1);
        $offset = ($currentPage - 1) * $perPage;

        $forms = DB::table('formlar')
            ->select('isim', 'dosya')
            ->where('durum','=','1')
            ->skip($offset)
            ->take($perPage)
            ->get();

        $totalForms = DB::table('formlar')->count();
        $lastPage = ceil($totalForms / $perPage);

        return view('formlar', compact('forms', 'currentPage', 'lastPage'));
    }
    public function searchTopluluk(Request $request)
    {
        $query = $request->input('q');
        $topluluklar = DB::table('topluluklar')
            ->where('isim', 'LIKE', "%{$query}%")
            ->select('id', 'isim', 'gorsel')
            ->get();

        return response()->json($topluluklar);
    }
    public function searchUye(Request $request)
    {
        $query = $request->input('q');
        $toplulukId = $request->input('topluluk_id');
        $ogrenciler = DB::table('ogrenci_bilgi')
            ->join('uyeler', 'uyeler.ogr_id', '=', 'ogrenci_bilgi.id')
            ->where('numara', 'LIKE', "%{$query}%")
            ->where('uyeler.durum', '=', '1')
            ->where('uyeler.top_id', '=',  "{$toplulukId}")
            ->select('uyeler.tarih as tarih', 'numara', 'isim', 'soyisim', 'tel', 'fak_ad as fakülte', 'bol_ad as bolum', 'uyeler.belge as belge','uyeler.rol as rol')
            ->get();
        return response()->json($ogrenciler);
    }
    public function searchApply(Request $request)
    {
        $query = $request->input('q');
        $toplulukId = $request->input('topluluk_id');
        $ogrenciler = DB::table('ogrenci_bilgi')
            ->join('uyeler', 'uyeler.ogr_id', '=', 'ogrenci_bilgi.id')
            ->where('numara', 'LIKE', "%{$query}%")
            ->where('uyeler.durum', '=', '0')
            ->where('uyeler.top_id', '=',  "{$toplulukId}")
            ->select('ogrenci_bilgi.id','uyeler.tarih as tarih', 'numara', 'isim', 'soyisim', 'tel', 'fak_ad as fakülte', 'bol_ad as bolum', 'uyeler.belge as belge','uyeler.rol as rol')
            ->get();
        return response()->json($ogrenciler);
    }
    public function toplulukEkle(Request $request) {
            $tc = $request->input('baskan_no');

            $curl = curl_init();
            //CURL SETUPLAYIN
            $response = curl_exec($curl);
            curl_close($curl);
            $data = json_decode($response, true);
            $yeniTarih = \Carbon\Carbon::createFromFormat('d.m.Y', $data[0]["KAY_TAR"])->format('Y-m-d');
            $ogrenci = DB::table('ogrenci_bilgi')->where('tc', $tc)->first();
            if(!$ogrenci) {
                $id = DB::table('ogrenci_bilgi')->insertGetId([
                    'numara' => $data[0]["OGR_NO"],
                    'tc' => $data[0]["TCK"],
                    'isim' => $data[0]["AD"],
                    'soyisim' => $data[0]["SOYAD"],
                    'fak_ad' => $data[0]["FAK_AD"],
                    'bol_ad' => $data[0]["BOL_AD"],
                    'prog_ad' => $data[0]["PROG_AD"],
                    'sınıf' => $data[0]["SINIF"],
                    'kay_tar' => $yeniTarih,
                    'ogrenim_durum' => $data[0]["OGRENIM_DURUM"],
                    'ogrenim_tip' => $data[0]["OGRENIM_TIP"],
                    'ayr_tar' => $data[0]["AYR_TAR"],
                    'tel' => $data[0]["TELEFON"],
                    'tel2' => $data[0]["TELEFON2"],
                    'eposta' => $data[0]["EPOSTA1"],
                    'eposta2' => $data[0]["EPOSTA2"],
                    'program_tip' => $data[0]["PROGRAM_TIP"],
                    'durum' => $data[0]["DURUM"]
                ]);
            }
            else{
                $id=$ogrenci->id;
            }
            if($id) {
                $belgeAdi = time() . '.' . $request->kurulus_belge->extension();
                $request->kurulus_belge->move(public_path('belgeler/kurulus'), $belgeAdi);

                $toplulukId = DB::table('topluluklar')->insertGetId([
                    'isim' => $request->isim,
                    'kurulus' => Carbon::now()
                ]);
                if($toplulukId){
                    $uye=DB::table('uyeler')->insert([
                        'ogr_id' => $id,
                        'top_id' => $toplulukId,
                        'tarih' => Carbon::now(),
                        'rol' => 2
                    ]);
                    if($uye){
                        return back()->with('success', 'Topluluk Eklendi, Başkan Atandı');
                    }
                    else{
                        return back()->with('success', 'Topluluk Eklendi.');

                    }
                }
                else{
                    return back()->with('success', 'Topluluk eklenemedi.');
                }
            }
            else{
                return back()->with('success', 'Öğrenci Bulunamadı.');
            }
    }
    public function formlistele()
    {
        $perPage = 10;
        $currentPage = request()->query('page', 1);
        $offset = ($currentPage - 1) * $perPage;

        $forms = DB::table('formlar')
            ->select('id','isim', 'dosya')
            ->where('durum','=','1')
            ->skip($offset)
            ->take($perPage)
            ->get();

        $totalForms = DB::table('formlar')->count();
        $lastPage = ceil($totalForms / $perPage);

        return view('denetim_formlar', compact('forms', 'currentPage', 'lastPage'));
    }
    public function formSil($id)
    {
        // Formu veritabanından bul
        $form = DB::table('formlar')->where('id', $id)->first();

        if (!$form) {
            return back()->with('danger', 'Form Bulunamadı.');
        }

        DB::table('formlar')->where('id', $id)->update(['durum' => 0]);

        return back()->with('success', 'Form Başarıyla Silindi');
    }
    public function topluluksil(Request $request)
    {
        $id = $request->input('id');

        $topluluk = Topluluk::find($id);
        if ($topluluk) {
            $topluluk->durum = 2;
            $topluluk->save();

            return response()->json([
                'success' => true,
                'message' => 'Topluluk başarıyla silindi.'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Topluluk silinemedi.'
        ]);
    }


}


