
<?php 
// F055B Testing FORM PASIEN PULANG
$nrmedis=$_GET['nrm'];
$sqlps="SELECT * FROM tbl_pendaftaran where id_registrasi='$_GET[nrek]' and no_rekamedis='$_GET[nrm]' ";
$hasilpsn=mysqli_query($koneksi,$sqlps);
while ($psn = mysqli_fetch_assoc($hasilpsn)) {
	$BPJS=$psn['pembiayaan'];
	$tanggalmasuk=$psn['tanggal_masuk'];
	$tanggalkeluar=date('Y-m-d');
    $dokterDPJP = $psn['dokter'];//new ad
    $pasienJk = $psn['jenis_kelamin'];//new ad
    $pasienPelayanan = $psn['tujuanasesmen'];//new add
}
$cekPasienPulang = cek_pasien_keluar($_GET['nrek']); 
$sqlPulang = "SELECT * FROM tbl_pasien_pulang WHERE id_registrasi = '$_GET[nrek]'"; 
$resultDtPulang = mysqli_query($koneksi, $sqlPulang);
$ketemuDtPulang = mysqli_fetch_assoc($resultDtPulang);

$sqlrungpsn="SELECT * FROM tbl_list_ruangpasien where id_registrasi='$_GET[nrek]' ";
$dtrpsn=mysqli_query($koneksi,$sqlrungpsn);
$ruangpasien = mysqli_fetch_assoc($dtrpsn);

$sqlKonsulpsn="SELECT * FROM tbl_konsultasi_pasien where id_registrasi='$_GET[nrek]' ";
$dtrpsnKonsul=mysqli_query($koneksi,$sqlKonsulpsn);
// Simpan ke array
$dataListKonsul = [];
while ($rowKOnsul = mysqli_fetch_assoc($dtrpsnKonsul)) {
    $dataListKonsul[] = $rowKOnsul;
}
//ambil data keperawatan
$adataquerykep="SELECT * FROM rekam_keperawatan where id_registrasi='$_GET[nrek]' and nama_form='F03' ";
$Ccekadataquerykep=mysqli_query($koneksi,$adataquerykep);
$hasilcekadataquerykep=mysqli_fetch_assoc($Ccekadataquerykep);


//////*******get resume
$db = $koneksi;

// Ambil parameter (GET)
$id_registrasi = isset($_GET['nrek']) ? intval($_GET['nrek']) : 0;
$no_rm = isset($_GET['nrm']) ? trim($_GET['nrm']) : '';

if ($id_registrasi <= 0 || $no_rm === '') {
    die("Parameter id_registrasi (nrek) dan no_rm (nrm) wajib diberikan di URL.");
}

// Ambil data resume jika ada
$resume = null;
$sql = "SELECT * FROM tbl_resume_medis_akhir WHERE id_registrasi = ? AND no_rm = ? ORDER BY id DESC LIMIT 1";
$stmt = $db->prepare($sql);
if (!$stmt) die("Prepare failed: " . $db->error);
$stmt->bind_param('is', $id_registrasi, $no_rm);
$stmt->execute();
$res = $stmt->get_result();
$resume = $res->fetch_assoc();
$stmt->close();

// Jika tidak ada resume, buat array kosong supaya form tetap menampilkan default
if (!$resume) {
    $resume = [
        'diagnosa_masuk'=>'', 'pembiayaan'=>'', 'riwayat_singkat'=>'',
        'pemeriksaan_fisik'=>'', 'hasil_pemeriksaan_penunjang'=>'',
        'terapi_pengobatan'=>'', 'tindakan'=>'', 'keadaan_pulang'=>'',
        'instruksi_pulang'=>'', 'cara_keluar'=>'', 'diagnosa_utama'=>'',
        'icd_utama'=>'', 'tgl_masuk'=>'', 'tgl_keluar'=>'', 'ruang_rawat'=>''
    ];
}

// Ambil diagnosis (utama & sekunder) dari tabel diagnosis jika ada
$diagnosa_utama = trim($resume['diagnosa_utama'] ?? '');
$icd_utama = trim($resume['icd_utama'] ?? '');
$diagnosa_sekunder = []; // array of ['diagnosa'=>..., 'icd'=>...]

if (!empty($resume['id'])) {
    $resume_id = intval($resume['id']);

    // 1) Jika diagnosa_utama kosong di tabel resume, ambil dari tbl_resume_medis_diagnosis jenis='utama'
    if ($diagnosa_utama === '' || $icd_utama === '') {
        $qU = "SELECT diagnosa, icd FROM tbl_resume_medis_diagnosis WHERE resume_id = ? AND jenis = 'utama' LIMIT 1";
        $stU = $db->prepare($qU);
        if ($stU) {
            $stU->bind_param('i', $resume_id);
            $stU->execute();
            $resU = $stU->get_result();
            if ($rowU = $resU->fetch_assoc()) {
                // hanya set jika field resume kosong
                if ($diagnosa_utama === '') $diagnosa_utama = $rowU['diagnosa'];
                if ($icd_utama === '') $icd_utama = $rowU['icd'];
            }
            $stU->close();
        }
    }

    // 2) Ambil semua diagnosa sekunder
    $q = "SELECT diagnosa, icd FROM tbl_resume_medis_diagnosis WHERE resume_id = ? AND jenis = 'sekunder' ORDER BY id ASC";
    $st = $db->prepare($q);
    if ($st) {
        $st->bind_param('i', $resume_id);
        $st->execute();
        $r = $st->get_result();
        while ($row = $r->fetch_assoc()) {
            $diagnosa_sekunder[] = ['diagnosa' => $row['diagnosa'], 'icd' => $row['icd']];
        }
        $st->close();
    }
}


// Ambil data pasien dasar (no_rekamedis, nama, tanggal_lahir, alamat, no_telp) dari tabel pasien jika Anda punya.
// Contoh asumsi: tabel tbl_pasien dengan kolom no_rekamedis = no_rm
$pasien = ['id_registrasi'=>$id_registrasi, 'no_rekamedis'=>$no_rm, 'nama_pasien'=>'-', 'jenis_kelamin'=>'-', 'tanggal_lahir'=>null, 'alamat'=>'', 'no_telp'=>''];
$psql = "SELECT * FROM tbl_pendaftaran WHERE id_registrasi = ? AND no_rekamedis = ? LIMIT 1";
$ps = $db->prepare($psql);
if ($ps) {
    $ps->bind_param('ss', $id_registrasi, $no_rm);
    $ps->execute();
    $pres = $ps->get_result();
    $prow = $pres->fetch_assoc();
    if ($prow) $pasien = $prow;
    $ps->close();
}

// helper untuk escaping
function e($s){ return htmlspecialchars($s ?? '', ENT_QUOTES); }
/////*******get data resume

?>

<?php if ($BPJS=='BPJS') { ?>
	
		<br>
		<i>PASIEN BPJS SILAHKAN MELAKUKAN PROSES TANGGAL PULANG</i>
		<!--<a style="color:white" target="_blank" href="?m=data-pasien-pulang-bpjs&xr=<?= $_GET['nrek'] ?>">*KLIK UNTUK PROSES TANGGAL PULANG</a>-->
	
<?php } ?>

<form method="POST">
	<table class="table">
	<input type="hidden" name="id_form" value="<?= $pilihform  ?>">
	<input type="hidden" name="nama_form" value="<?= $pilihform  ?>">
	<input type="hidden" name="id_registrasi" value="<?= $xr  ?>">

	<!-- input  ke table -->
	<input type="hidden" name="id_pasien" value="<?= $idpasienbpjs ?>">
	<input type="hidden" name="no_rekamedis" value="<?= $nrmedis ?>">
	<input type="hidden" name="jam" value="<?= $jam ?>">
	<input type="hidden" name="tgl_berobat" value="<?= $tgl_harini ?>">
	<tbody>
		<tr>
			<td>Ringkasan Pulang</td>
			<td>
				<label>Tanggal Masuk</label>
				<input class="form-control" type="text" readonly name="tanggal_masuk" required value="<?= $tanggalmasuk; ?>">
				<br>

				<label>Tanggal Pulang/keluar</label>
				<input class="form-control" type="date" name="tanggal_keluar" required="" value="<?= date('Y-m-d'); ?>">
				<br>

                <label>Jam Pulang/keluar</label>
                <input class="form-control" type="text" id="timeInputkeluar" name="jam_pulang" required>
                <script>
                    // Fungsi untuk memformat waktu dalam format 1-24 tanpa nol di depan
                    function formatTime(inputId) {
                        const now = new Date();
                        let hours = now.getHours(); // Dapatkan jam saat ini (0-23)
                        let minutes = now.getMinutes(); // Dapatkan menit saat ini (0-59)
                        minutes = minutes < 10 ? '0' + minutes : minutes; // Tambahkan nol di depan menit jika perlu
                        document.getElementById(inputId).value = hours + ':' + minutes;
                    }

                    // Panggil fungsi saat halaman dimuat
                    window.onload = function() {
                        formatTime('timeInputkeluar'); // Format waktu untuk input mulai
                    };
                </script>
				<br>
				<?php
                    $date = $tanggalmasuk; // ambil tanggal dengan format kata
                    $datetime = DateTime::createFromFormat('d-M-Y', $date);
                    $tglkonversiakhir = $datetime->format('Y-m-d'); // hasil konversi menjadi angka

                    $book = new DateTime($tglkonversiakhir);
                    $today = new DateTime('today');

                    // Menghitung selisih hari total antara dua tanggal
                    $diff = $today->diff($book);
                    $totalDays = $diff->days;

                    $datalamaperawatan = "$totalDays hari";
                 ?>
				<label>Lama Perawatan</label>
				<input class="form-control" type="text" name="lama_dirawat" readonly required value="<?= $datalamaperawatan; ?>">
				<input class="form-control" type="hidden" name="lama_hari_perawatan" required value="<?= $d; ?>">
				<br>

				<label>Ruang Rawat</label><br>		
				<?= rrawat($iddreg=$xr); ?>
                <input class="form-control" type="hidden" name="kode_kelas_pasien" value="<?= $ruangpasien['kode_kelas']; ?>">
			</td>
		</tr>
        		
		<tr>
			<td>Diagnosa</td>
			<td>
				<?php
                $qrmedisDiagnosa = mysqli_query($koneksi, "SELECT * FROM rekam_medis where id_registrasi='$xr' and nama_form='F08' and diagnosa!='' ORDER BY id DESC limit 1"); $diagnosa = mysqli_fetch_assoc($qrmedisDiagnosa);
                $qrmedisDiagnosaUt = mysqli_query($koneksi, "SELECT * FROM rekam_medis where id_registrasi='$xr' and nama_form='F08' and diagnosa_utama!='' ORDER BY id DESC limit 1"); $diagnosaUt = mysqli_fetch_assoc($qrmedisDiagnosaUt); 
                ?>
                <?= $diagnosa['diagnosa'] ?>-<?= get_diagnosa_icd($diagnosa['diagnosa']) ?>
				<?= $diagnosaUt['diagnosa_utama'] ?>-<?= get_diagnosa_icd($diagnosaUt['diagnosa_utama']) ?>
			</td>
		</tr>
		<tr>
			<td>Tindakan</td>
			<td>
				<?php 
                //tindakan
                $dnstindakan='<i>-</i>';
                $qrmedistindakan = mysqli_query($koneksi, "SELECT * FROM rekam_medis where id_registrasi='$xr' and nama_form='F09' ");
                while($datamedistindakan = mysqli_fetch_assoc($qrmedistindakan)){
                    if (empty($datamedistindakan['tindakan'])) {
                        $dnstindakan='<i>-</i>'; 
                    }else{
                        if ($BPJS=='BPJS'){
                            $qrmedistindakan = mysqli_query($koneksi, "SELECT * FROM ICD9_KEMKES where kode='$datamedistindakan[tindakan]' "); while($datamedistindakanps = mysqli_fetch_assoc($qrmedistindakan))
                            {$dnstindakan=$datamedistindakanps['kode'].'-'.$datamedistindakanps['keterangan']; }
                        }elseif ($BPJS=='Mandiri'){
                            $qrmedistindakan = mysqli_query($koneksi, "SELECT * FROM ICD9_KEMKES where kode='$datamedistindakan[tindakan]' "); while($datamedistindakanps = mysqli_fetch_assoc($qrmedistindakan))
                            {$dnstindakan=$datamedistindakanps['kode'].'-'.$datamedistindakanps['keterangan']; }
                        }

                        //ind
                        $kdpros=$datamedistindakanps['kode'];
                        $nmpros=$datamedistindakanps['keterangan'];
                    }
                    //tindakan
                    echo !empty($dnstindakan) ? $dnstindakan : '<i>-</i>';
                }
                ?>
			</td>
		</tr>
		<tr>
			<td><b>Keluhan Utama</b></td>
			<td>
                <?= $hasilcekadataquerykep['keluhan_utama'] ?? 'Belum di input'; ?>
			</td>
		</tr>
		<tr>
			<td><b>Alasan Masuk Dirawat</b></td>
			<td>
                <textarea class="form-control" required name="alasan_masuk_dirawat" placeholder="Contoh: Batuk."></textarea>
			</td>
		</tr>
				
		<tr>
			<td>Kondisi Pulang</td>
			<td>
				<textarea class="form-control" type="text" required name="kondisi_pulang" placeholder="Contoh: Pasien pulang dalam keadaan sadar penuh, suhu tubuh normal, dan tidak ada keluhan."></textarea>
			</td>
		</tr>
		<tr>
			<td><b>Instruksi Pulang</b></td>
			<td>
                <textarea class="form-control" type="text" name="ins_pulang" placeholder="Contoh: Kontrol ulang ke poli dalam 7 hari / Lanjutkan obat sampai habis . Jika sesak atau demam tinggi, segera ke IGD." ></textarea>
			</td>
		</tr>
        <tr>
			<td><b>Status Pulang</b></td>
			<td>
				<select class="form-control" required name="status_pulang">
				<option value=''>-- Pilih Status --</option>
		        <?php
		          $sqlddatapaca="SELECT * FROM tbl_pascapulang_bpjs ";
		          $hslpacaoplang=mysqli_query($koneksi,$sqlddatapaca);
		          while ($dtpascaplang = mysqli_fetch_array($hslpacaoplang)) {
		         ?>
	        	<option value="<?php echo $dtpascaplang['kode'];?>"><?php echo $dtpascaplang['kode'];?>. <?php echo $dtpascaplang['nama'];?></option>
	        	<?php } ?>
	        </select>
			</td>
		</tr>
		<tr>
			<td><b>Cara Keluar/Pulang</b></td>
			<td>
				<select class="form-control" required name="cara_keluar">
				<option value=''>-- Pilih Status --</option>
		        <?php
		          $sqlddatapaca="SELECT * FROM tbl_carakeluar_bpjs ";
		          $hslpacaoplang=mysqli_query($koneksi,$sqlddatapaca);
		          while ($dtpascaplang = mysqli_fetch_array($hslpacaoplang)) {
		         ?>
	        	<option value="<?php echo $dtpascaplang['kode'];?>"><?php echo $dtpascaplang['kode'];?>. <?php echo $dtpascaplang['nama'];?></option>
	        	<?php } ?>
	        </select>
			</td>
		</tr>
		
        <hr>

        <tr>
			<td><b>Konsul Dokter</b></td>
			<td>
                <?php
                if (mysqli_num_rows($dtrpsnKonsul) > 0) {
                    foreach ($dataListKonsul as $datak) {
                        $jenis_pengajuan = $datak['jenis_pengajuan'];
                        $waktuKonsul = $datak['tanggal'] ."-". $datak['jam'];
                        $dokter = get_sdm($datak['dokter_tujuan']);
                        echo $dokter['nama']." <br> ";
                        echo "$jenis_pengajuan - ";
                        echo "waktu $waktuKonsul <br>";
                    }
                }else{
                   echo "Tidak ada data konsul"; 
                }
                ?>
			</td>
		</tr>
		<tr>
			<td><b>Dokter Penanggung Jawab</b></td>
			<td>
				<select class="form-control" required name="dokter">
		        <?php
			        $sqld="SELECT * FROM tbl_sdm where id='$dokterDPJP'";
			        $hasild=mysqli_query($koneksi,$sqld);
			        while ($dktr = mysqli_fetch_array($hasild)) {
		         ?>
		        	<option value="<?php echo $dktr['id'];?>"><?php echo $dktr['nama'];?> (<?php echo $dktr['kompetensi'];?>)</option>
		        	<?php } ?>
		        </select>
			</td>
		</tr>
	</tbody>
</table>

<div class="row">
    <div class="col-md-12">
      <?php if($cekPasienPulang>0){ ?>
        <button class="btn btn-danger pull-right" disabled> PASIEN SUDAH DIPULANGKAN  </button><br><br>
      <?php }else{ ?>
			<?php if ($BPJS=='BPJS'){ ?>
				<button type="submit" name="asesmenpasienpulang" class="btn btn-primary pull-right" > PROSES PULANG </button><br><br>
			<?php }else{ ?>
				<button type="submit" name="asesmenpasienpulang" class="btn btn-warning pull-right" > PROSES PULANG  </button><br><br>
			<?php } ?>
	  <?php } ?>
    </div>
</div>
</form>

<br>
<?php if ($level != '') { ?>
<hr style="border: 3px solid white;">

<div class="row">
    <div class="col-md-12">
      <div class="card">
		<div class="card-body">

<style>
.diagnosa-item {
    display: flex;
    align-items: flex-start;
    gap: 5px;
    margin-bottom: 5px;
}

.diagnosa-item textarea {
    flex: 1; /* biar textarea lebar penuh */
    resize: vertical; /* tetap bisa di-resize manual */
}

.diagnosa-item .icd-input {
    width: 80px;
    flex-shrink: 0; /* jangan mengecil */
}

.diagnosa-item .btn-hapus {
    flex-shrink: 0;
}
.footer {
    text-align: center;
    margin-top: 50px;
}
</style>
<form method="POST" action="" id="form_cetak">
<b class="mb-4" style="font-size:20px">RESUME MEDIS</b>
<button type="button" onclick="printForm()" class="btn btn-success btn-sm float-right no-print mb-2">Cetak</button>
<table class="table table-bordered" width="100%"> 
    <tr>
        <td>Nama Pasien:</td>
        <td><?= e($pasien['nama_pasien']); ?></td>
        <td rowspan="2">Tanggal Lahir: <br> <?= e($pasien['tanggal_lahir']); ?></td>
        <td rowspan="2">Jenis Kelamin:</td>
        <td>
            <input type="checkbox" class="checkbox" <?= ( ($pasienJk ?? '') === 'L' ) ? 'checked' : '' ?>> Laki-laki <br>
            <input type="checkbox" class="checkbox" <?= ( ($pasienJk ?? '') === 'P' ) ? 'checked' : '' ?>> Perempuan
        </td>
    </tr>
    <tr>
        <td>No. RM:</td>
        <td><?= e($pasien['no_rekamedis']); ?></td>
        <td>Pelayanan: <?= e($pasienPelayanan); ?></td>
    </tr>
    <tr>
        <td>Umur:</td>
        <td>
            <?php if (!empty($pasien['tanggal_lahir'])): ?>
                <?= date_diff(date_create($pasien['tanggal_lahir']), date_create('today'))->y; ?> tahun
            <?php else: ?>
                -
            <?php endif; ?>
        </td>
        <td colspan="3">Alamat: <?= e($pasien['alamat'] ?: 'Belum input'); ?> / Telp: <?= e($pasien['no_telp'] ?: '08888'); ?></td>
    </tr>
</table>

<table class="table table-bordered">
    <tr>
        <td>Tanggal/Jam Masuk:</td>
        <td><?= e($ketemuDtPulang['tgl_masuk'] ?? ''); ?></td>
        <td>Tanggal/Jam Keluar:</td>
        <td><?= date('d-F-Y', strtotime($ketemuDtPulang['tgl_pulang'])); ?></td>
        <td>Ruang Rawat:</td>
        <td>
            <?php if($pasienPelayanan=='RAWAT INAP'){ ?>
                <?= rrawat($id_registrasi); ?>
            <?php }else{ ?>
                <?= $pasienPelayanan; ?>
            <?php } ?>
        </td>
    </tr>
    <tr>
        <td>Diagnosis Masuk:</td>
        <td colspan="5">
            <textarea name="diagnosa_masuk" class="form-control" rows="2"><?php $getDiagAwalParam = getDiagnosabyParameter($id_registrasi, 'awal');
            if ($getDiagAwalParam) {
                // Ambil keterangan ICD dari diagnosa yang didapat
                $diagAwalICD = get_diagnosa_icd($getDiagAwalParam);
                echo htmlspecialchars($getDiagAwalParam . " - " . $diagAwalICD);
            } else {
                echo "-";
            } ?></textarea>
        </td>
    </tr>
    <tr>
        <td>Penanggung Pembiayaan:</td>
        <td colspan="5">
            <textarea name="pembiayaan" readonly class="form-control auto-expand" rows="2"><?= ($BPJS === 'BPJS') ? 'JKN' : e($BPJS ?? '') ?></textarea>
        </td>
    </tr>
    <tr>
        <td>Riwayat Singkat :</td>
        <td colspan="5">
            <textarea name="riwayat_singkat" class="form-control auto-expand" rows="3"><?= e($resume['riwayat_singkat'] ?? '') ?></textarea>
        </td>
        
    </tr>
    <tr>
        <td>Pemeriksaan Fisik:</td>
        <td colspan="5">
            <textarea name="pemeriksaan_fisik" class="form-control auto-expand" rows="3"><?= e($resume['pemeriksaan_fisik'] ?? '') ?></textarea>
        </td>
    </tr>
    <tr>
        <td>Pemeriksaan Penunjang:</td>
        <td colspan="5">
            <textarea name="hasil_pemeriksaan_penunjang" class="form-control auto-expand" rows="3"><?= e($resume['hasil_pemeriksaan_penunjang'] ?? '') ?></textarea>
        </td>
    </tr>
    <tr>
        <td>Terapi/Pengobatan:</td>
        <td colspan="5">
            <textarea name="terapi_pengobatan" class="form-control auto-expand" rows="3"><?= e($resume['terapi_pengobatan'] ?? '') ?></textarea>
        </td>
    </tr>
    <tr>
        <td>Prosedur/Tindakan:</td>
        <td colspan="5">
            <textarea name="tindakan" class="form-control auto-expand" rows="3"><?= e($resume['tindakan'] ?? '') ?></textarea>
        </td>
    </tr>

    <tr>
        <td>Diagnosis Utama</td>
        <td style="width: 60%;" colspan="4">
            <textarea name="diagnosa_utama" class="form-control" style="width:100%;" placeholder="Tuliskan diagnosis utama"><?php $getDiagUtamaParam = getDiagnosabyParameter($id_registrasi, 'utama');
            if ($getDiagUtamaParam) {
                // Ambil keterangan ICD dari diagnosa yang didapat
                $diagUtamaICD = get_diagnosa_icd($getDiagUtamaParam);
                echo htmlspecialchars($getDiagUtamaParam . " - " . $diagUtamaICD);
            } else {
                echo "-";
            } ?></textarea>
        </td>
        <td>
            ICD <input type="text" name="icd_utama" class="form-control" style="width:100%;" placeholder="ICD 10" value="<?= e($getDiagUtamaParam) ?>">
        </td>
    </tr>

    <tr>
        <td>Diagnosis Sekunder</td>
        <td colspan="5">
            <div id="diagnosa-utama-wrapper">
                <?php
                // ambil nilai (harus berupa array atau kosong)
                $getDiagTambhParams = getDiagnosabyParameter($id_registrasi, 'tambahan');
        
                if (!empty($getDiagTambhParams) && is_array($getDiagTambhParams)) {
                    $count = 0;
                    foreach ($getDiagTambhParams as $item) {
                        $count++;
                        if ($count > 5) break;
        
                        // fleksibel: $item bisa string atau array(['diagnosa'=>..., 'icd'=>...])
                        if (is_array($item)) {
                            $diagText = $item['diagnosa'] ?? '';
                            $icdVal = $item['icd'] ?? '';
                        } else {
                            $diagText = (string)$item;
                            $icdVal = $diagText; // asumsi item adalah kode/icd jika hanya string
                        }
        
                        // ambil keterangan ICD (opsional) untuk tampilan di textarea
                        $diagKeterangan = $diagText !== '' ? get_diagnosa_icd($diagText) : '';
                        ?>
                        <div class="diagnosa-item">
                            <textarea name="diagnosa_tambahan[]" rows="2" class="form-control auto-expand" placeholder="Tuliskan diagnosis"><?= e($diagText . ($diagKeterangan ? ' - ' . $diagKeterangan : '')) ?></textarea>
                            ICD
                            <input type="text" name="icd_tambahan[]" class="form-control" style="width:80px; display:inline-block;" placeholder="ICD 10" value="<?= e($icdVal) ?>">
                        </div>
                        <?php
                    }
                } else {
                    // jika tidak ada: tampilkan 1 baris kosong
                    ?>
                    <div class="diagnosa-item">
                        <textarea name="diagnosa_tambahan[]" rows="2" class="form-control auto-expand" placeholder="Tuliskan diagnosis"></textarea>
                        ICD
                        <input type="text" name="icd_tambahan[]" class="form-control" style="width:80px; display:inline-block;" placeholder="ICD 10" value="">
                    </div>
                    <?php
                }
                ?>
            </div>
            <div style="margin-top:6px;">
                <button type="button" id="add-diagnosa" class="btn btn-primary no-print">+ Tambah Diagnosis</button>
                <span style="color:#fff; margin-left:10px;"></span>
            </div>
           
        </td>
    </tr>

    <tr>
        <?php
        // tanggal pulang terakhir
        $qrmedipulangpasien = mysqli_query($koneksi, "SELECT * FROM tbl_pasien_pulang WHERE id_registrasi='$_GET[nrek]' ORDER BY id DESC LIMIT 1");
        if (mysqli_num_rows($qrmedipulangpasien) > 0) {
            $datatanggalpulangpasien = mysqli_fetch_assoc($qrmedipulangpasien);
            $statuspulang = $datatanggalpulangpasien['status_pulang'];
            $instruksipulang = $datatanggalpulangpasien['instruksi_pulang'];
            $cara_keluar_pulang = $datatanggalpulangpasien['cara_keluar'];
            $kondisi_keluar_pulang = $datatanggalpulangpasien['kondisi_pulang'];
        }
        ?>
        <td>Status Pulang:</td>
        <td colspan="5">
            Keadaan Pulang: 
            <?php $queryCKPCS = mysqli_query($koneksi, "SELECT * FROM tbl_pascapulang_bpjs where kode='$statuspulang' ");
	              $dataQrCKPCS = mysqli_fetch_assoc($queryCKPCS);
	        ?><?= $dataQrCKPCS['nama']; ?><br>
            Instruksi Pulang: <?= nl2br(htmlspecialchars($instruksipulang ?? '-')) ?><br>
            Cara Keluar: 
            <?php $queryCK = mysqli_query($koneksi, "SELECT * FROM tbl_carakeluar_bpjs where kode='$cara_keluar_pulang' ");
	              $dataQrCK = mysqli_fetch_assoc($queryCK);
	        ?><?= $dataQrCK['nama']; ?><br>
			Kondisi Pulang: <?=$kondisi_keluar_pulang?>
        </td>
    </tr>
</table>

<?php if (!empty($resume['diagnosa_masuk'])): ?>
    <!-- Kalau sudah ada isinya -->
    <button type="button" class="btn btn-secondary no-print" disabled>
        Resume Sudah Disimpan
    </button>
<?php else: ?>
    <!-- Kalau masih kosong -->
    <?php if($level==='dokter' || $userid==36){ ?>
        <button type="submit" name="simpan_resume_medis" class="btn btn-warning no-print">
            Simpan Resume
        </button>
    <?php } ?>
<?php endif; ?>

<div class="footer">
    <p>DPJP,</p>
    <?php $nmdpjp=get_dokter($dokterDPJP); echo $nmdpjp['nama'] ?>
    <br>
</div>
</form>

<?php
$dtrpasiendataNREK = $_GET['nrek'] ?? '';
$dtrpasiendataNRM = $_GET['nrm'] ?? '';
$dtrpasiendata = $dtrpasiendataNRM.$dtrpasiendataNREK;
?>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const wrapper = document.getElementById("diagnosa-sekunder-wrapper");
    const addBtn = document.getElementById("add-diagnosa");

    addBtn.addEventListener("click", function () {
        let count = wrapper.querySelectorAll(".diagnosa-item").length;
        if (count < 7) {
            const newItem = document.createElement("div");
            newItem.classList.add("diagnosa-item");
            newItem.style.marginTop = "5px";
            newItem.innerHTML = `
                <textarea name="diagnosa_tambahan[]" rows="2" class="form-control" placeholder="Tuliskan diagnosis sekunder"></textarea>
                ICD
                <input type="text" name="icd_tambahan[]" class="form-control" style="width:80px; display:inline-block;" placeholder="ICD 10">
                <button type="button" class="btn btn-xs btn-danger remove-diagnosa">Hapus</button>
            `;
            wrapper.appendChild(newItem);

            // tombol hapus
            newItem.querySelector(".remove-diagnosa").addEventListener("click", function () {
                wrapper.removeChild(newItem);
            });
        } else {
            alert("Maksimal diagnosis sekunder dibatasi!");
        }
    });
});
</script>
<script>
// fungsi auto expand untuk semua textarea
function autoExpand(el) {
  el.style.height = 'auto';           // reset dulu
  el.style.height = el.scrollHeight + 'px'; // sesuaikan tinggi isi
}

// jalankan untuk semua textarea dengan class auto-expand
document.addEventListener('DOMContentLoaded', function() {
  document.querySelectorAll('textarea.auto-expand').forEach(function(el) {
    autoExpand(el); // saat halaman load
    el.addEventListener('input', function() { autoExpand(el); }); // saat user mengetik
  });
});

function printForm() {
    var form = document.getElementById("form_cetak");
    var printWindow = window.open("", "", "width=800,height=550");
    
    // Clone form biar tidak mengubah aslinya
    var clone = form.cloneNode(true);

    // Ubah semua textarea jadi div agar tampil penuh
    clone.querySelectorAll("textarea").forEach(function(txt){
        var div = document.createElement("div");
        div.style.whiteSpace = "pre-wrap"; // jaga line break
        div.innerText = txt.value;
        txt.parentNode.replaceChild(div, txt);
    });
    printWindow.document.write('<html><head><title>Cetak Resume <?= htmlspecialchars($dtrpasiendata) ?></title>');
    printWindow.document.write(`
        <style>
            table, td, th {
                border: 1px solid #000;
                border-collapse: collapse;
            }
            td {
                padding: 5px;
            }
            @media print {
                .no-print {
                    display: none !important;
                }
                textarea {
                    border: none !important;
                    overflow: visible !important;
                    resize: none !important;
                }
                .footer {
                    text-align: center;
                    margin-top: 20px;
                }
            }
        </style>
    `);
    
    printWindow.document.write('</head><body>');
    printWindow.document.write(clone.innerHTML);
    // Ambil parameter dari URL
    const urlParams = new URLSearchParams(window.location.search);
    const nrm  = urlParams.get('nrm') ?? '-';
    const nrek = urlParams.get('nrek') ?? '-';
    // footer QRCode
    printWindow.document.write(`
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"><\/script>
        <div class="footer">
            <div id="qrcode"></div>
        </div>
        <script>
            const nrm  = "${nrm}";
            const nrek = "${nrek}";
            const qrData = "NRM: " + nrm + " | NREK: " + nrek;
            new QRCode(document.getElementById("qrcode"), {
              text: qrData,
              width: 100,
              height: 100
            });
        <\/script>
    `);
    printWindow.document.write('</body></html>');
    printWindow.document.close();
    printWindow.print();
}
</script>
				</div>
	  	</div>
    </div>
</div>

<?php
if (isset($_POST['simpan_resume_medis'])) {

// ------------------ CEK KONEKSI ------------------
if (!isset($koneksi)) {
    die('Error: koneksi database ($koneksi) tidak ditemukan.');
}

$db = $koneksi; // objek mysqli

// ------------------ AMBIL & NORMALISASI INPUT ------------------
$id_registrasi = $_GET['nrek'];
$no_rm = $_GET['nrm'];

$diagnosa_masuk = trim($_POST['diagnosa_masuk'] ?? '');
$pembiayaan = trim($_POST['pembiayaan'] ?? '');
$riwayat_singkat = trim($_POST['riwayat_singkat'] ?? '');
$pemeriksaan_fisik = trim($_POST['pemeriksaan_fisik'] ?? '');
$hasil_pemeriksaan_penunjang = trim($_POST['hasil_pemeriksaan_penunjang'] ?? '');
$terapi_pengobatan = trim($_POST['terapi_pengobatan'] ?? '');
$tindakan = trim($_POST['tindakan'] ?? '');
$keadaan_pulang = trim($_POST['keadaan_pulang'] ?? '');
$instruksi_pulang = trim($_POST['instruksi_pulang'] ?? '');
$cara_keluar = trim($_POST['cara_keluar'] ?? '');

$diagnosa_utama = trim($_POST['diagnosa_utama'] ?? '');
$icd_utama = strtoupper(trim($_POST['icd_utama'] ?? ''));

$diagnosa_tambahan = $_POST['diagnosa_tambahan'] ?? [];
$icd_tambahan = $_POST['icd_tambahan'] ?? [];

$maxSek = max(count($diagnosa_tambahan), count($icd_tambahan));
for ($i = 0; $i < $maxSek; $i++) {
    if (!isset($diagnosa_tambahan[$i])) $diagnosa_tambahan[$i] = '';
    if (!isset($icd_tambahan[$i])) $icd_tambahan[$i] = '';
    $diagnosa_tambahan[$i] = trim($diagnosa_tambahan[$i]);
    $icd_tambahan[$i] = strtoupper(trim($icd_tambahan[$i]));
}

// ------------------ TRANSAKSI & SIMPAN ------------------
try {
    $db->begin_transaction();

    $sql = "INSERT INTO tbl_resume_medis_akhir (
        id_registrasi, no_rm, diagnosa_masuk, pembiayaan, riwayat_singkat,
        pemeriksaan_fisik, hasil_pemeriksaan_penunjang,
        terapi_pengobatan, tindakan, keadaan_pulang,
        instruksi_pulang, cara_keluar, icd_utama
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = $db->prepare($sql);
    if (!$stmt) throw new Exception("Prepare gagal (resume): " . $db->error);

    $stmt->bind_param(
        'sssssssssssss',
        $id_registrasi, $no_rm, $diagnosa_masuk, $pembiayaan, $riwayat_singkat,
        $pemeriksaan_fisik, $hasil_pemeriksaan_penunjang,
        $terapi_pengobatan, $tindakan, $keadaan_pulang,
        $instruksi_pulang, $cara_keluar, $icd_utama
    );

    if (!$stmt->execute()) throw new Exception("Execute gagal (resume): " . $stmt->error);
    $resume_id = $stmt->insert_id;
    $stmt->close();

    // diagnosa utama
    if ($diagnosa_utama !== '' || $icd_utama !== '') {
        $sql2 = "INSERT INTO tbl_resume_medis_diagnosis (id_registrasi, resume_id, jenis, diagnosa, icd) VALUES (?,?, 'utama', ?, ?)";
        $st2 = $db->prepare($sql2);
        if (!$st2) throw new Exception("Prepare gagal (diagnosa utama): " . $db->error);
        $st2->bind_param('iiss', $id_registrasi, $resume_id, $diagnosa_utama, $icd_utama);
        if (!$st2->execute()) throw new Exception("Execute gagal (diagnosa utama): " . $st2->error);
        $st2->close();
    }

    // diagnosa sekunder
    $sql3 = "INSERT INTO tbl_resume_medis_diagnosis (id_registrasi, resume_id, jenis, diagnosa, icd) VALUES (?,?, 'sekunder', ?, ?)";
    $st3 = $db->prepare($sql3);
    if (!$st3) throw new Exception("Prepare gagal (diagnosa sekunder): " . $db->error);

    for ($i = 0; $i < $maxSek; $i++) {
        $d = $diagnosa_tambahan[$i];
        $c = $icd_tambahan[$i];
        if ($d === '' && $c === '') continue;
        $st3->bind_param('iiss', $id_registrasi, $resume_id, $d, $c);
        if (!$st3->execute()) throw new Exception("Execute gagal (diagnosa sekunder row $i): " . $st3->error);
    }
    $st3->close();

    $db->commit();


} catch (Exception $ex) {
    $db->rollback();
    // tampilkan pesan error untuk debugging (atau log)
    echo "Gagal menyimpan: " . $ex->getMessage();
    // jika ingin lihat error mysqli terakhir:
    // echo "\nDB error: " . $db->error;
    exit;
}

}
?>

		    </div>
	  	</div>
    </div>
</div>
<?php } //tutup if dpjp ?>



<?php
if (isset($_POST['asesmenpasienpulang'])) {

	$id_registrasi = $_POST['id_registrasi'];
	$status_pulang = $_POST['status_pulang'];
	$dpjp = $_POST['dokter'];
	$kondisi_pulang = $_POST['kondisi_pulang'];
	$tglPulangPasien = $_POST['tanggal_keluar'];
    $tglMasukPasien = $_POST['tanggal_masuk'];
    $kode_kelas_pasien = $_POST['kode_kelas_pasien'];
    $jamPulangPasien=$_POST['jam_pulang'];
    $ins_pulang = $_POST['ins_pulang'];
    $cara_keluar=$_POST['cara_keluar'];
    $alasan_masuk_dirawat=$_POST['alasan_masuk_dirawat'];

	$sqlpulng  = mysqli_query($koneksi, "INSERT INTO tbl_pasien_pulang SET 
            kode_id='$kode_id',
			proses='P',
			id_registrasi='$id_registrasi',
			tgl_masuk='$tglMasukPasien',
            tgl_pulang='$tglPulangPasien',
            jam_pulang='$jamPulangPasien',
			dpjp='$dpjp',
			status_pulang='$status_pulang',
            kondisi_pulang='$kondisi_pulang',
			cara_keluar='$cara_keluar',
			alasan_masuk_dirawat='$alasan_masuk_dirawat',
            instruksi_pulang = '$ins_pulang'
	");
 	
	$sqlinsertprosespulang = mysqli_query($koneksi, "UPDATE tbl_pendaftaran SET 
			proses='PULANG'
			WHERE id_registrasi='$id_registrasi'
	");

    //cek dataruangan
    $sqltersedia="SELECT * FROM data_ruangan where kodekelas='$kode_kelas_pasien' ";
    $hsltersedia=mysqli_query($koneksi,$sqltersedia);
    $ruangtersedia = mysqli_fetch_assoc($hsltersedia);
    $rkelasoke = $ruangtersedia['tersedia'];
    $updhasilakhirtersedia = $rkelasoke +1;
    //kembalikan kelas hanya saat pertamakali pasien dipulangkan
    $queryUpdtersediaruang=" UPDATE data_ruangan SET 
        tersedia='$updhasilakhirtersedia'
        where kodekelas='$kode_kelas_pasien' ";
    mysqli_query($koneksi, $queryUpdtersediaruang);
	
	//echo "<script>window.location = '?m=data-pasien-keluar';</script>";
    setFlasher('success','Pasien selesai perawatan', 1500);
}
?>
