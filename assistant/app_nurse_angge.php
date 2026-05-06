<?php
// ============================================================
// NURSE ANGGE — SSCMS Mobile App (app.php)
// Single-file PHP app for ICCBI Smart School Clinic
// All DB + functions integrated inline
// ============================================================
session_start();
date_default_timezone_set('Asia/Manila');

// ─── DATABASE CONFIG ────────────────────────────────────────
// Replace with your actual credentials
if (!defined('DB_HOST')) define('DB_HOST', 'localhost');
if (!defined('DB_USER')) define('DB_USER', 'root');
if (!defined('DB_PASS')) define('DB_PASS', '');
if (!defined('DB_NAME')) define('DB_NAME', 'u456627434_sscms_system');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER, DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                 PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
            );
        } catch (PDOException $e) {
            return null; // Demo mode if DB unavailable
        }
    }
    return $pdo;
}

// ─── CSRF TOKEN ──────────────────────────────────────────────
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// ─── AJAX HANDLERS ──────────────────────────────────────────
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) || !empty($_GET['ajax']) || !empty($_POST['ajax'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'] ?? $_GET['action'] ?? '';

    // ── Get fresh CSRF token ──
    if ($action === 'get_csrf') {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        echo json_encode(['success' => true, 'token' => $_SESSION['csrf_token']]);
        exit;
    }

    // ── Get Appointments — reads from the shared appointments table ──
    if ($action === 'get_appointments') {
        $db = getDB();
        if (!$db) { echo json_encode(['success' => false, 'message' => 'DB unavailable']); exit; }
        try {
            $stmt = $db->query("
                SELECT id, patient_name, category, phone, appointee,
                       appointment_date, appointment_time, reason, status, created_at
                FROM appointments
                ORDER BY appointment_date DESC, appointment_time ASC
                LIMIT 50
            ");
            echo json_encode(['success' => true, 'data' => $stmt->fetchAll()]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }

    // ── AI Chat ──
    if ($action === 'ai_chat') {
        $msg = trim($_POST['message'] ?? '');
        if (empty($msg)) { echo json_encode(['success' => false]); exit; }
        echo json_encode(['success' => true, 'reply' => nurseAnggeResponse($msg), 'timestamp' => date('h:i A')]);
        exit;
    }

    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

// ─── NURSE ANGGE AI ──────────────────────────────────────────
function nurseAnggeResponse($msg) {
    $m = strtolower($msg);

    if (preg_match('/^(hi|hello|hey|kamusta|good\s*(morning|afternoon|evening)|magandang)/i', $m)) {
        $h = (int)date('H');
        $g = $h < 12 ? 'Good morning' : ($h < 18 ? 'Good afternoon' : 'Good evening');
        return "$g! 👋 I'm **Nurse Angge**, your AI health companion at ICCBI Clinic.\n\nI can help with:\n🩺 Health symptoms & first aid\n💊 Medication guidance\n🥗 Nutrition & wellness\n😴 Sleep & mental health\n📅 Appointment booking\n🚨 Emergency guidance\n\nWhat can I help you with today? 💙";
    }
    if (preg_match('/fever|lagnat|high temp|init ng katawan/i', $m)) {
        return "🌡️ **Fever Guidance**\n\nFever = body temp above **37.5°C (99.5°F)**\n\n**What to do:**\n• Rest and drink plenty of fluids\n• Paracetamol 500mg if ≥38°C (take with food)\n• Cool damp cloth on forehead\n• Wear light, breathable clothing\n• Monitor every 2-4 hours\n\n⚠️ **Go to clinic IMMEDIATELY if:**\n• Temp exceeds 39.5°C (103.1°F)\n• Fever lasts more than 3 days\n• Accompanied by stiff neck, rash, or difficulty breathing\n• Child under 3 months with any fever\n\nWould you like to **book a clinic appointment**? 🏥";
    }
    if (preg_match('/headache|sakit ng ulo|migraine|ulo.*sakit/i', $m)) {
        return "🧠 **Headache Relief**\n\n**Common causes:** stress, dehydration, eye strain, poor posture, lack of sleep\n\n**Immediate steps:**\n• Drink 1–2 glasses of water right now\n• Rest in a quiet, dimly lit room\n• Gently massage temples in circular motions\n• Apply cold or warm compress on forehead\n• Paracetamol or ibuprofen for pain\n• Close eyes and practice deep breathing\n\n**Prevention:**\n• Drink 8+ glasses water daily\n• Take screen breaks every 20 minutes\n• Maintain proper posture\n• Get 7–9 hours of sleep\n\n⚠️ **See a doctor if:** sudden severe headache, fever with stiff neck, or persists 72+ hours.";
    }
    if (preg_match('/stomach|tiyan|abdomen|diarrhea|pagtatae|vomit|sumasama|mual|ulcer|LBM/i', $m)) {
        return "🫀 **Stomach Discomfort Guide**\n\n**Indigestion / Acidity:**\n• Avoid spicy, fatty, acidic foods\n• Take antacids (Kremil-S, Maalox)\n• Eat smaller, more frequent meals\n• Don't lie down immediately after eating\n\n**Diarrhea / Vomiting:**\n• 🥛 ORS (oral rehydration solution) — sip slowly\n• Bland diet: bananas, rice, toast, boiled chicken\n• Avoid dairy, high-fiber, greasy foods\n• Probiotics can help restore gut flora\n\n**Stomach Pain Relief:**\n• Warm compress on abdomen\n• Mefenamic acid for cramps (take with food)\n• Avoid NSAIDs if ulcer-related\n\n⚠️ **Visit clinic for:** severe pain, blood in stool/vomit, fever, or symptoms lasting 24+ hours.";
    }
    if (preg_match('/cough|ubo|sipon|colds|runny|sore throat|flu/i', $m)) {
        return "🤧 **Cough & Cold Care**\n\n**Self-care at home:**\n• 😴 Rest is your most powerful medicine\n• 💧 Drink warm fluids — water, soup, ginger tea\n• 🍋 Honey + lemon in warm water soothes throat\n• 🌬️ Steam inhalation for congestion relief\n• 🚭 Avoid smoke, dust, cold air\n\n**OTC medications:**\n• Paracetamol — fever & throat pain\n• Antihistamine (Cetirizine) — runny nose\n• Carbocisteine — mucus relief\n• Lozenges — sore throat\n\n**Do NOT:**\n• ❌ Self-medicate with antibiotics (viral infection!)\n• ❌ Share utensils or towels with others\n\n⚠️ **See a doctor:** cough 2+ weeks, difficulty breathing, blood in phlegm, or high fever.";
    }
    if (preg_match('/wound|sugat|cut|injury|sprain|pilas|dugo|bleeding|bali/i', $m)) {
        return "🩹 **First Aid for Wounds & Injuries**\n\n**Minor Cuts & Wounds:**\n1. Wash hands thoroughly first!\n2. Rinse wound with clean running water for 5 min\n3. Apply gentle pressure to stop bleeding\n4. Apply Betadine or antiseptic\n5. Cover with sterile bandage\n6. Change dressing daily; keep dry\n\n**Sprains (RICE Method):**\n• 🔴 **R**est — avoid putting weight on it\n• 🧊 **I**ce — 20 min on, 20 min off\n• 🩹 **C**ompression — elastic bandage\n• 🦵 **E**levation — raise above heart level\n\n**Go to clinic IMMEDIATELY for:**\n• Deep or gaping wounds (may need sutures)\n• Animal or human bites\n• Bleeding won't stop after 10 min\n• Signs of infection: pus, increasing redness, warmth\n• Possible broken bone (deformity, severe swelling)";
    }
    if (preg_match('/stress|anxious|anxiety|depress|sad|burnout|pagod|nalulungkot|mental|hindi.*kaya|ayaw|masamang.*pakiramdam/i', $m)) {
        return "💙 **You're Not Alone — I Hear You**\n\nFeeling this way is completely valid. School life can be overwhelming, and it's okay to not be okay sometimes.\n\n**Immediate relief techniques:**\n• 🌬️ **4-7-8 Breathing:** Inhale 4s → Hold 7s → Exhale 8s (repeat 4x)\n• ☀️ Step outside for 5-10 min of sunlight\n• ✍️ Journal your thoughts — write it out\n• 🎵 Listen to calming music\n• 🤝 Talk to someone you trust\n\n**Longer-term wellness:**\n• Regular sleep schedule (7-9 hrs)\n• Physical activity — even short walks\n• Limit social media time\n• Eat balanced meals\n\n**At ICCBI, you can:**\n• Talk to the clinic nurse (confidentially)\n• Request guidance counselor referral\n\n📞 **PH Crisis Hotlines:**\n• NCMH: (02) 8989-8727\n• Hopeline: 2919 (Globe/TM)\n\nYou matter more than you know. 💛";
    }
    if (preg_match('/blood pressure|BP|hypertension|mataas.*dugo|low blood|hypotension/i', $m)) {
        return "💓 **Blood Pressure Guide**\n\n**Normal:** 90–120 / 60–80 mmHg\n\n📊 **Stages:**\n• Elevated: 120-129 / <80\n• Stage 1 HBP: 130-139 / 80-89\n• Stage 2 HBP: ≥140 / ≥90\n• Hypertensive Crisis: >180 / >120 🚨\n\n**If BP is HIGH:**\n• Sit/lie down quietly for 10 min; recheck\n• Avoid salt, caffeine, alcohol\n• Take prescribed medication if any\n• Deep breathing to relax\n• Seek help if ≥160/100 or symptomatic\n\n**If BP is LOW:**\n• Lie down; elevate legs\n• Drink water or electrolyte solution\n• Eat small, frequent meals\n• Rise slowly from sitting/lying\n• Avoid hot showers or prolonged standing\n\n🏥 Our clinic can measure your BP anytime during school hours — no appointment needed for BP checks!";
    }
    if (preg_match('/tooth|ngipin|dental|dentist|toothache|sakit.*ngipin/i', $m)) {
        return "🦷 **Toothache First Aid**\n\n**For immediate pain relief:**\n• Rinse with warm salt water (½ tsp salt + 8oz water)\n• Apply clove oil on cotton ball to affected tooth\n• Cold compress on jaw/cheek (15 min on/off)\n• Mefenamic acid or Ibuprofen for pain\n• Avoid extremely hot, cold, or sweet foods\n\n**Common causes:**\n• Tooth decay / cavities\n• Cracked or broken tooth\n• Gum infection\n• Wisdom tooth eruption\n• Exposed root (sensitivity)\n\n**Dental hygiene tips:**\n• Brush 2x daily, floss once\n• Use fluoride toothpaste\n• Limit sugary food and drinks\n• Replace toothbrush every 3 months\n\n⚠️ **Visit clinic/dentist for:** severe persistent pain, swollen jaw/gums, fever, difficulty opening mouth, or visible abscess.";
    }
    if (preg_match('/book|appoint|schedule|visit clinic|magpa.check|reserve|slot/i', $m)) {
        return "📅 **How to Book a Clinic Appointment**\n\nTap the **📅 Appointments** tab at the bottom to book!\n\n**You'll need to provide:**\n• ✅ Full name\n• ✅ Category (Student/Staff/etc.)\n• ✅ Phone number (for SMS confirmation)\n• ✅ Preferred date & time\n• ✅ Appointee (Doctor / Nurse / Dentist)\n• ✅ Reason for visit\n\n**Clinic Hours:**\n• Mon–Fri: 8:00 AM – 5:00 PM\n• Saturday: 9:00 AM – 12:00 PM\n• Sunday: Closed\n\n**Same-day visits:** The clinic also accepts walk-in patients during clinic hours!\n\nIs there anything else I can help you with? 💙";
    }
    if (preg_match('/emergency|hindi.*huminga|seizure|unconscious|walang.*malay|heart attack|puso|nalugmok|choking/i', $m)) {
        return "🚨 **EMERGENCY — ACT IMMEDIATELY**\n\n📞 **Call 911 or get to the clinic NOW**\n\n**If not breathing/no pulse → Start CPR:**\n1. Call for help loudly\n2. Position: flat on back on firm surface\n3. Place heel of hand on center of chest\n4. Press hard and fast: 100-120/min, 2 inches deep\n5. 30 compressions → 2 rescue breaths (if trained)\n6. Don't stop until help arrives\n\n**If choking:**\n• 5 back blows between shoulder blades\n• 5 abdominal thrusts (Heimlich maneuver)\n• Alternate until object dislodges or help arrives\n\n**If seizure:**\n• Clear area of sharp/hard objects\n• Lay person on side\n• Do NOT restrain or put anything in mouth\n• Time the seizure; call for help if >5 min\n\n**Never leave the person alone!** Alert nearest clinic staff immediately.";
    }
    if (preg_match('/vitamin|nutrition|nutri|diet|pagkain|healthy food|immune|supplement/i', $m)) {
        return "🥗 **Nutrition & Wellness for Students**\n\n**Key nutrients you need:**\n• 🍊 **Vitamin C** — immunity: citrus, ampalaya, kamatis\n• ☀️ **Vitamin D** — bones & mood: sun 15 min/day, fish, eggs\n• 🥩 **Iron** — energy & focus: red meat, malunggay, spinach\n• 🧠 **B-vitamins** — brain power: whole grains, nuts, legumes\n• 🥛 **Calcium** — strong bones: milk, kesong puti, taho\n\n**Healthy eating habits:**\n• 🍳 NEVER skip breakfast — it powers your learning!\n• 🥦 At least 3 servings of vegetables daily\n• 💧 8-10 glasses of water\n• 🍱 Eat every 4-5 hours; don't skip meals\n• 🚫 Limit: instant noodles, sugary drinks, fast food\n\n**Budget-friendly superfoods:** malunggay, kamote tops, kangkong, saging, eggs — all affordable and nutrient-packed!";
    }
    if (preg_match('/sleep|tulog|gising|insomnia|pagod|tired|antok/i', $m)) {
        return "😴 **Sleep Health Guide**\n\n**How much do you need?**\n• Ages 13–18: **8–10 hours**\n• Ages 18+: **7–9 hours**\n\n**Why sleep matters:**\n• Consolidates memory for better learning\n• Repairs tissues and boosts immunity\n• Regulates mood and stress hormones\n• Maintains healthy weight and metabolism\n\n**Tips for better sleep:**\n• Consistent bedtime — even on weekends!\n• No screens 30–60 min before bed (blue light blocks melatonin)\n• Cool, dark, quiet bedroom environment\n• Avoid caffeine after 2 PM\n• Light stretching or reading before bed\n• No heavy meals 2 hours before sleeping\n\n**Can't sleep?** Try:\n• 4-7-8 breathing technique\n• Write a to-do list to clear your mind\n• Progressive muscle relaxation\n• Guided meditation (YouTube/Spotify)\n\nPersistent insomnia? Book a clinic appointment! 📅";
    }
    if (preg_match('/hiv|aids/i', $m)) {
        return "🔴 **HIV Awareness — Important for Filipinos**\n\n⚠️ The Philippines currently has the **fastest-growing HIV epidemic in Asia**, with a 500% increase in cases among youth aged 15-25.\n\n**Transmission routes:**\n• Unprotected sexual contact\n• Sharing needles\n• Mother-to-child (pregnancy/birth/breastfeeding)\n• Blood transfusion (rare — screened)\n\n**NOT transmitted by:** hugging, sharing food, mosquito bites, saliva\n\n**Prevention:**\n• ✅ Use condoms consistently and correctly\n• ✅ Know your status — get tested (free at DOH facilities)\n• ✅ Avoid sharing needles\n• ✅ Pre-exposure prophylaxis (PrEP) if at risk\n\n**Treatment:** HIV is manageable — ART (antiretroviral therapy) allows people to live long, healthy lives. EARLY detection is KEY.\n\n📞 AIDS Society of PH: (02) 521-0932\nAll inquiries are **100% confidential**. 🔒";
    }
    if (preg_match('/period|regla|menstrual|dysmenorrhea|cramps|puson/i', $m)) {
        return "🌸 **Menstrual Health Guide**\n\n**For period cramps (dysmenorrhea):**\n• 🌡️ Heating pad or hot water bottle on lower abdomen\n• 💊 Mefenamic acid (Ponstan) 500mg with food — start at first sign of pain\n• 🧘 Gentle yoga poses: child's pose, knees-to-chest\n• 🫖 Warm ginger or chamomile tea\n• 😌 Rest — it's okay to rest!\n\n**Tracking your cycle:**\n• Normal cycle: 21–35 days\n• Normal period: 3–7 days\n• Slight variations month to month are normal\n\n**See the clinic if:**\n• Extremely heavy bleeding (pad every hour)\n• Severe cramps unresponsive to meds\n• Periods lasting >8 days\n• Sudden changes in cycle regularity\n• Symptoms of anemia: paleness, fatigue, dizziness\n\n💙 Your health and comfort matter. Visit the clinic anytime — our nurse staff are understanding and supportive.";
    }
    if (preg_match('/who are you|sino ka|about you|nurse angge|what can you/i', $m)) {
        return "👩‍⚕️ **About Nurse Angge**\n\nI'm **Nurse Angge** — your AI-powered health assistant for the **ICCBI Smart School Clinic Management System (SSCMS)**!\n\n**What I can help with:**\n🩺 Symptom assessment & guidance\n💊 First aid instructions\n🥗 Nutrition & wellness advice\n😴 Sleep & mental health support\n🔴 HIV & disease awareness\n📅 Clinic appointment guidance\n🚨 Emergency response steps\n\n**My knowledge covers:**\n• Common school health issues\n• Philippine health context & news\n• Evidence-based health recommendations\n• DOH Philippines guidelines\n\n⚕️ **Important:** I provide general health information and guidance only. For diagnosis, treatment, and medical records — please visit our clinic and consult our healthcare professionals.\n\n**Clinic Hours:** Mon–Fri 8AM–5PM | Sat 9AM–12PM\n\nHow can I assist you today? 💙";
    }
    if (preg_match('/thank|salamat|maraming salamat/i', $m)) {
        return "You're very welcome! 😊💙\n\nRemember — your health is your most valuable asset. Take care of yourself:\n\n✅ Stay hydrated (8+ glasses/day)\n✅ Get enough sleep (7-9 hours)\n✅ Eat balanced meals\n✅ Move your body daily\n✅ Manage stress proactively\n\nAnd whenever you need help, I'm here 24/7! The clinic is also always available during school hours. 🏥\n\nIs there anything else I can help you with? 🌟";
    }

    // Default
    return "🤔 I want to give you the most helpful answer!\n\nCould you share more details? For example:\n• What symptoms are you experiencing?\n• How long have you had them?\n• Any other concerns?\n\n**Or ask me about:**\n🌡️ Fever · 🧠 Headache · 🤧 Cough/Colds\n🦷 Toothache · 💓 Blood Pressure\n😴 Sleep · 🥗 Nutrition · 💙 Mental Health\n📅 Booking appointments\n\nI'm here to help! Just describe what you're feeling. 💙";
}

// ─── HEALTH NEWS ─────────────────────────────────────────────
function getHealthNews() {
    return [
        ['title'=>'DOH Implements Zero Balance Billing in Gov\'t Hospitals','summary'=>'Indigent patients no longer need politicians\' guarantee letters. The 2026 GAA now mandates equal access to medical assistance for all.','source'=>'DOH Philippines','tag'=>'Policy','date'=>'Jan 8, 2026','icon'=>'🏥'],
        ['title'=>'HIV Cases Among PH Youth Surge 500% — DOH Raises Alarm','summary'=>'Philippines records highest new HIV cases in Western Pacific. Youth aged 15-25 most affected. Free confidential testing available at DOH facilities.','source'=>'GMA News','tag'=>'🚨 Alert','date'=>'Dec 24, 2025','icon'=>'🔴'],
        ['title'=>'PhilHealth Fully Covers Influenza Treatment','summary'=>'Flu and influenza-like illness treatment now fully covered. Members reminded to use their PhilHealth benefits for flu-related hospitalization.','source'=>'PNA','tag'=>'Benefit','date'=>'Dec 15, 2025','icon'=>'💊'],
        ['title'=>'DOH Targets 12M Filipinos for TB Screening by 2026','summary'=>'Under PhilSTEP2 2025–2030, the DOH and WHO aim to screen 12 million Filipinos for Tuberculosis, doubling the budget for TB services.','source'=>'WHO Philippines','tag'=>'Program','date'=>'Nov 20, 2025','icon'=>'🩺'],
        ['title'=>'Pneumonia 4th Leading Cause of Death — Vaccines Urgently Needed','summary'=>'Over 46,000 Filipinos died from pneumonia in Jan–Jul 2025. Health experts urge wider vaccination campaigns especially for elderly and children.','source'=>'PNA','tag'=>'⚠️ Warning','date'=>'Oct 14, 2025','icon'=>'⚠️'],
        ['title'=>'Youth Smoking Doubles in 2 Years — Illegal Vapes Blamed','summary'=>'Public health advocates call for urgent enforcement against illegal cigarettes and e-cigarettes fueling nicotine addiction among Filipino teens.','source'=>'PNA','tag'=>'Youth','date'=>'Sep 30, 2025','icon'=>'🚭'],
    ];
}

// ─── BUILD PAGE ──────────────────────────────────────────────
$news = getHealthNews();
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="theme-color" content="#0369a1">
<script>(function(){var t=localStorage.getItem('sscms_theme');if(t==='dark')document.documentElement.setAttribute('data-theme','dark');})()</script>
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="description" content="Nurse Angge — ICCBI Smart Clinic AI Health Assistant">
<title>Nurse Angge · SSCMS</title>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;500;600;700;800;900&family=Space+Grotesk:wght@400;500;600;700&family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css" rel="stylesheet">
<script src="https://use.fontawesome.com/releases/v6.3.0/js/all.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>

<style>
:root {
    --a1: #0369a1;
    --a2: #0ea5e9;
    --ar: #0369a1;
    --grad: linear-gradient(135deg, var(--a1), var(--a2));
    --grad-r: linear-gradient(225deg, var(--a1), var(--a2));
    --r: 18px; --rs: 12px; --rx: 8px;
    --sh: 0 4px 20px rgba(0,0,0,.09);
    --sh-lg: 0 10px 40px rgba(0,0,0,.14);
    --dur: .22s; --ease: cubic-bezier(.4,0,.2,1);
    --fs: 15.5px;

    --bg: #f0f4f8;
    --bg-c: #ffffff;
    --bg-n: #ffffff;
    --tx: #0f172a;
    --tx2: #64748b;
    --tx3: #94a3b8;
    --bdr: rgba(0,0,0,.06);
    --inp: #f1f5f9;
    --bub-ai: #ffffff;
}
[data-theme="dark"] {
    --bg: #0d1117; --bg-c: #161c24; --bg-n: #161c24;
    --tx: #e5edf5; --tx2: #8896a5; --tx3: #5a6878;
    --bdr: rgba(255,255,255,.07); --inp: #1e2530; --bub-ai: #1e2530;
}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0}
html,body{height:100%;width:100%;overflow:hidden;font-family:'Nunito',sans-serif;font-size:var(--fs);background:var(--bg);color:var(--tx);-webkit-font-smoothing:antialiased;-webkit-tap-highlight-color:transparent;touch-action:pan-y}
h1,h2,h3,h4,h5{font-family:'Poppins','Space Grotesk',sans-serif}
*{scrollbar-width:none} *::-webkit-scrollbar{display:none}

/* ── APP SHELL ── */
#app{display:flex;flex-direction:column;height:100vh;height:100dvh;max-width:430px;margin:0 auto;position:relative;background:var(--bg);overflow:hidden}

/* ── HEADER ── */
.hdr{background:var(--grad);padding:env(safe-area-inset-top,0) 0 0;flex-shrink:0;position:relative;overflow:hidden;z-index:10}
.hdr::before,.hdr::after{content:'';position:absolute;border-radius:50%;background:rgba(255,255,255,.07)}
.hdr::before{top:-50px;right:-50px;width:180px;height:180px}
.hdr::after{bottom:-30px;left:10px;width:120px;height:120px}
.hdr-inner{display:flex;align-items:center;justify-content:space-between;padding:13px 18px;position:relative;z-index:2}
.hdr-left{display:flex;align-items:center;gap:11px}
.hdr-logo{width:42px;height:42px;background:rgba(255,255,255,.18);border-radius:14px;display:flex;align-items:center;justify-content:center;font-size:20px;border:1.5px solid rgba(255,255,255,.25);backdrop-filter:blur(8px);flex-shrink:0}
.hdr-t h1{font-size:17px;font-weight:700;color:#fff;letter-spacing:-.3px}
.hdr-t p{font-size:11px;color:rgba(255,255,255,.7);font-weight:500}
.hdr-badge{display:flex;align-items:center;gap:5px;background:rgba(255,255,255,.15);padding:5px 11px;border-radius:20px;font-size:11px;font-weight:700;color:#fff;border:1px solid rgba(255,255,255,.2);backdrop-filter:blur(8px)}
.hdr-dot{width:7px;height:7px;border-radius:50%;background:#4ade80;animation:pdot 2s ease-in-out infinite}
@keyframes pdot{0%,100%{opacity:1;transform:scale(1)}50%{opacity:.5;transform:scale(1.6)}}

/* ── CONTENT ── */
#content{flex:1;overflow-y:auto;overflow-x:hidden;-webkit-overflow-scrolling:touch;scroll-behavior:smooth}

/* ── TABS ── */
.tv{display:flex;flex-direction:column}
.tv>div{display:none}
.tv>div.on{display:block}
#tab-ai.on{display:flex;flex-direction:column;height:calc(100dvh - 68px - 70px)}
#tab-ai.on .chat-scroll{flex:1;overflow-y:auto;-webkit-overflow-scrolling:touch}

/* ── BOTTOM NAV ── */
.bnav{display:flex;background:var(--bg-n);border-top:1px solid var(--bdr);padding:8px 0 max(8px,env(safe-area-inset-bottom));flex-shrink:0;position:relative;z-index:20;box-shadow:0 -3px 15px rgba(0,0,0,.05)}
.nbtn{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:3px;background:none;border:none;cursor:pointer;padding:5px 3px;color:var(--tx3);transition:all var(--dur) var(--ease);-webkit-tap-highlight-color:transparent}
.nbtn svg{width:22px;height:22px;stroke:currentColor;fill:none;stroke-width:2;transition:all var(--dur) var(--ease)}
.nbtn i{font-size:21px;transition:all var(--dur) var(--ease);line-height:1}
.nbtn span{font-size:9.5px;font-weight:700;transition:all var(--dur) var(--ease)}
.nbtn.on{color:var(--a1)}
.nbtn.on svg{transform:scale(1.12);stroke-width:2.5}
.nbtn.on i{transform:scale(1.15)}
.nbtn.on span{font-weight:900}
.nav-ind{position:absolute;top:0;height:3px;background:var(--grad);border-radius:0 0 4px 4px;width:33.333%;transition:left .3s var(--ease)}
/* ── DARK MODE TOGGLE ── */
.hdr-dm{width:34px;height:34px;border-radius:50%;background:rgba(255,255,255,.15);border:1.5px solid rgba(255,255,255,.25);backdrop-filter:blur(8px);display:flex;align-items:center;justify-content:center;font-size:15px;cursor:pointer;transition:all var(--dur);margin-right:8px;flex-shrink:0}
.hdr-dm:active{transform:scale(.9)}
.hdr-right{display:flex;align-items:center}

/* ── CARDS ── */
.card{background:var(--bg-c);border-radius:var(--r);box-shadow:var(--sh);border:1px solid var(--bdr);overflow:hidden}
.card-p{padding:16px}
.card-sm{border-radius:var(--rs)}

/* ════════════════════════════
   HOME TAB
════════════════════════════ */
#tab-home{padding:16px;padding-bottom:24px}

.greeting{background:var(--grad);border-radius:var(--r);padding:20px;margin-bottom:16px;position:relative;overflow:hidden;color:#fff}
.greeting::after{content:'🏥';position:absolute;right:14px;bottom:6px;font-size:52px;opacity:.12}
.g-time{font-size:10.5px;font-weight:800;opacity:.8;text-transform:uppercase;letter-spacing:.8px;margin-bottom:5px}
.g-name{font-size:21px;font-weight:700;letter-spacing:-.3px;margin-bottom:3px}
.g-sub{font-size:12.5px;opacity:.8}

.qa-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:16px}
.qa{display:flex;flex-direction:column;align-items:center;gap:6px;background:var(--bg-c);border:1px solid var(--bdr);border-radius:var(--rs);padding:13px 6px;cursor:pointer;transition:all var(--dur) var(--ease);box-shadow:0 2px 8px rgba(0,0,0,.04);-webkit-tap-highlight-color:transparent}
.qa:active{transform:scale(.94);background:color-mix(in srgb,var(--ar) 8%,var(--bg-c))}
.qa-ico{font-size:22px;color:var(--a1)}
.qa-lbl{font-size:9.5px;font-weight:800;color:var(--tx2);text-align:center;line-height:1.2}

.grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:16px}
.info-tile{background:var(--bg-c);border:1px solid var(--bdr);border-radius:var(--rs);padding:13px 12px;display:flex;flex-direction:column;gap:5px}
.it-ico{font-size:18px;color:var(--a1)}
.it-lbl{font-size:9.5px;font-weight:800;color:var(--tx3);text-transform:uppercase;letter-spacing:.4px}
.it-val{font-size:13px;font-weight:800;color:var(--tx)}

.tip-card{background:var(--grad);border-radius:var(--r);padding:17px;margin-bottom:16px;color:#fff;position:relative;overflow:hidden}
.tip-card::before{content:'';position:absolute;top:-30px;right:-30px;width:110px;height:110px;border-radius:50%;background:rgba(255,255,255,.1)}
.tip-lbl{font-size:10px;font-weight:800;opacity:.8;text-transform:uppercase;letter-spacing:.8px}
.tip-title{font-size:15.5px;font-weight:800;margin-top:4px;letter-spacing:-.2px}
.tip-body{font-size:12.5px;opacity:.9;margin-top:6px;line-height:1.5}

.sec-hdr{display:flex;align-items:center;justify-content:space-between;margin-bottom:11px}
.sec-ttl{font-size:14.5px;font-weight:800;color:var(--tx);letter-spacing:-.2px}
.sec-badge{font-size:10px;font-weight:800;padding:3px 9px;border-radius:20px;background:var(--a1);color:#fff}

.news-card{background:var(--bg-c);border:1px solid var(--bdr);border-radius:var(--rs);padding:13px;margin-bottom:9px;cursor:pointer;transition:all var(--dur) var(--ease);display:flex;gap:12px;align-items:flex-start;box-shadow:0 2px 8px rgba(0,0,0,.04)}
.news-card:active{transform:scale(.98)}
.news-ico{font-size:26px;flex-shrink:0;margin-top:2px}
.news-c{flex:1;min-width:0}
.news-tag{font-size:9.5px;font-weight:900;text-transform:uppercase;letter-spacing:.4px;color:var(--a1);margin-bottom:3px}
.news-ttl{font-size:13px;font-weight:800;line-height:1.35;color:var(--tx)}
.news-sum{font-size:11.5px;color:var(--tx2);margin-top:5px;line-height:1.45}
.news-meta{font-size:10.5px;color:var(--tx3);margin-top:5px;font-weight:600}

.gospel-card{background:var(--bg-c);border:1px solid var(--bdr);border-radius:var(--rs);padding:18px;text-align:center;margin-bottom:8px}
.gospel-ico{font-size:30px;margin-bottom:9px}
.gospel-v{font-size:13px;font-style:italic;color:var(--tx);line-height:1.6}
.gospel-r{font-size:11px;color:var(--tx3);margin-top:7px;font-weight:800}

/* ════════════════════════════
   AI CHAT TAB
════════════════════════════ */
.chat-mini-hdr{background:var(--bg-c);border-bottom:1px solid var(--bdr);padding:11px 16px;display:flex;align-items:center;gap:10px;flex-shrink:0}
.ai-av-sm{width:38px;height:38px;background:var(--grad);border-radius:13px;display:flex;align-items:center;justify-content:center;font-size:19px;flex-shrink:0}
.ai-nm h3{font-size:14px;font-weight:800;color:var(--tx)}
.ai-nm p{font-size:11px;color:var(--tx3);font-weight:500}
.online-badge{margin-left:auto;font-size:9.5px;font-weight:800;padding:3px 9px;border-radius:20px;background:rgba(74,222,128,.12);color:#16a34a;border:1px solid rgba(74,222,128,.25)}

.chat-scroll{padding:14px 14px 10px;display:flex;flex-direction:column;gap:10px}

.msg-row{display:flex;align-items:flex-end;gap:8px}
.msg-row.user{flex-direction:row-reverse}
.m-av{width:30px;height:30px;border-radius:11px;display:flex;align-items:center;justify-content:center;font-size:15px;flex-shrink:0;background:var(--grad)}
.m-av.u{background:linear-gradient(135deg,#6366f1,#8b5cf6)}
.bubble{max-width:79%;padding:10px 13px;border-radius:16px;font-size:13.5px;line-height:1.55;color:var(--tx);white-space:pre-line;word-break:break-word}
.bubble.ai{background:var(--bub-ai);border:1px solid var(--bdr);border-bottom-left-radius:4px;box-shadow:0 2px 8px rgba(0,0,0,.05)}
.bubble.user{background:var(--a1);color:#fff;border-bottom-right-radius:4px}
.bubble strong{font-weight:900}
.m-time{font-size:9.5px;color:var(--tx3);padding:0 3px;flex-shrink:0}

.chat-chips{display:flex;gap:7px;overflow-x:auto;padding:8px 14px;flex-shrink:0;border-top:1px solid var(--bdr);background:var(--bg)}
.chip-s{white-space:nowrap;font-size:11.5px;font-weight:800;padding:6px 12px;border-radius:20px;background:var(--bg-c);border:1px solid var(--bdr);color:var(--tx2);cursor:pointer;transition:all var(--dur) var(--ease);flex-shrink:0}
.chip-s:active{background:var(--a1);color:#fff;border-color:var(--a1)}

.chat-inp-area{display:flex;align-items:center;gap:9px;padding:10px 14px;background:var(--bg-c);border-top:1px solid var(--bdr);flex-shrink:0;padding-bottom:max(10px,env(safe-area-inset-bottom))}
#chat-input{flex:1;background:var(--inp);border:1.5px solid var(--bdr);border-radius:22px;padding:10px 15px;font-family:'Nunito',sans-serif;font-size:13.5px;color:var(--tx);outline:none;resize:none;min-height:42px;max-height:90px;line-height:1.4;transition:border-color var(--dur)}
#chat-input:focus{border-color:var(--a1)}
#chat-input::placeholder{color:var(--tx3)}
.send-btn{width:42px;height:42px;border-radius:50%;background:var(--grad);border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;flex-shrink:0;transition:all var(--dur);box-shadow:0 3px 12px rgba(0,0,0,.15)}
.send-btn:active{transform:scale(.88)}
.send-btn svg{width:18px;height:18px;stroke:#fff;fill:none;stroke-width:2.5}

.typing-wrap{display:flex;align-items:center;gap:4px;padding:6px 0}
.td{width:7px;height:7px;border-radius:50%;background:var(--tx3);animation:ty 1.2s ease-in-out infinite}
.td:nth-child(2){animation-delay:.2s}.td:nth-child(3){animation-delay:.4s}
@keyframes ty{0%,60%,100%{transform:translateY(0)}30%{transform:translateY(-6px)}}

/* ════════════════════════════
   APPOINTMENTS TAB
════════════════════════════ */
#tab-appt{padding:16px;padding-bottom:28px}

.appt-hero{background:var(--grad);border-radius:var(--r);padding:18px;margin-bottom:16px;color:#fff;position:relative;overflow:hidden}
.appt-hero::after{content:'📅';position:absolute;right:14px;bottom:6px;font-size:46px;opacity:.13}
.appt-hero h2{font-size:19px;font-weight:700}
.appt-hero p{font-size:12.5px;opacity:.8;margin-top:3px}

.fg{margin-bottom:12px}
.fl{display:block;font-size:10.5px;font-weight:900;color:var(--tx2);margin-bottom:5px;text-transform:uppercase;letter-spacing:.4px}
.req{color:#ef4444}
.fc{width:100%;background:var(--inp);border:1.5px solid var(--bdr);border-radius:var(--rs);padding:10px 13px;font-family:'Nunito',sans-serif;font-size:14px;color:var(--tx);outline:none;transition:border-color var(--dur),box-shadow var(--dur);-webkit-appearance:none;appearance:none}
.fc:focus{border-color:var(--a1);box-shadow:0 0 0 3px color-mix(in srgb,var(--ar) 15%,transparent)}
.fc.err{border-color:#ef4444;box-shadow:0 0 0 3px rgba(239,68,68,.12)}
textarea.fc{resize:vertical;min-height:76px;line-height:1.5}
.fg2{display:grid;grid-template-columns:1fr 1fr;gap:10px}

.chips-r{display:flex;flex-wrap:wrap;gap:6px;margin-bottom:8px}
.chip-r{font-size:11px;font-weight:800;padding:4px 10px;border-radius:20px;background:var(--inp);border:1px solid var(--bdr);color:var(--tx2);cursor:pointer;transition:all var(--dur);-webkit-tap-highlight-color:transparent}
.chip-r.sel,.chip-r:active{background:var(--a1);color:#fff;border-color:var(--a1)}

.btn-sub{width:100%;padding:14px;background:var(--grad);color:#fff;border:none;border-radius:var(--rs);font-family:'Space Grotesk',sans-serif;font-size:15px;font-weight:700;cursor:pointer;transition:all var(--dur);box-shadow:0 4px 16px rgba(0,0,0,.18);margin-top:4px;letter-spacing:-.2px}
.btn-sub:active{transform:scale(.98);opacity:.9}
.btn-sub:disabled{opacity:.6;cursor:not-allowed}

.appt-item{background:var(--bg-c);border:1px solid var(--bdr);border-radius:var(--rs);padding:13px;margin-bottom:8px;display:flex;align-items:center;gap:12px;box-shadow:0 2px 8px rgba(0,0,0,.04)}
.sd{width:8px;height:8px;border-radius:50%;flex-shrink:0}
.s-p{background:#f59e0b}.s-a{background:#22c55e}.s-c{background:#ef4444}.s-d{background:#6366f1}
.ai-info2{flex:1;min-width:0}
.an{font-size:13.5px;font-weight:800;color:var(--tx)}
.ad{font-size:11.5px;color:var(--tx3);margin-top:2px}
.sbadge{font-size:9.5px;font-weight:800;padding:3px 8px;border-radius:20px;flex-shrink:0}
.b-p{background:rgba(245,158,11,.12);color:#d97706}
.b-a{background:rgba(34,197,94,.12);color:#16a34a}
.b-c{background:rgba(239,68,68,.12);color:#dc2626}
.b-d{background:rgba(99,102,241,.12);color:#4f46e5}


/* ── MODALS ── */
.modal-ol{position:fixed;inset:0;background:rgba(0,0,0,.5);backdrop-filter:blur(6px);z-index:100;display:flex;align-items:flex-end;justify-content:center;opacity:0;pointer-events:none;transition:opacity var(--dur)}
.modal-ol.open{opacity:1;pointer-events:all}
.modal-sh{background:var(--bg-c);border-radius:24px 24px 0 0;width:100%;max-width:430px;padding:24px 20px;text-align:center;transform:translateY(30px);transition:transform .3s var(--ease);max-height:80vh;overflow-y:auto}
.modal-ol.open .modal-sh{transform:translateY(0)}
.mh{width:36px;height:4px;background:var(--bdr);border-radius:4px;margin:0 auto 20px}
.m-ico-w{width:64px;height:64px;border-radius:20px;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;font-size:32px}
.m-ttl{font-size:18px;font-weight:800;color:var(--tx);letter-spacing:-.3px}
.m-txt{font-size:13px;color:var(--tx2);margin-top:8px;line-height:1.6}
.m-btn{display:inline-flex;align-items:center;gap:7px;width:100%;justify-content:center;background:var(--grad);color:#fff;border:none;border-radius:var(--rs);padding:13px;margin-top:18px;font-family:'Space Grotesk',sans-serif;font-size:14px;font-weight:700;cursor:pointer;transition:all var(--dur)}
.m-btn:active{transform:scale(.97)}

/* ── TOAST ── */
#toast{position:fixed;bottom:80px;left:50%;transform:translateX(-50%) translateY(20px);background:var(--tx);color:var(--bg-c);padding:10px 18px;border-radius:22px;font-size:12.5px;font-weight:800;box-shadow:var(--sh-lg);z-index:200;opacity:0;transition:all .3s var(--ease);white-space:nowrap;max-width:85vw;display:flex;align-items:center;gap:7px;pointer-events:none}
#toast.show{opacity:1;transform:translateX(-50%) translateY(0)}
#toast.success{background:#16a34a;color:#fff}
#toast.error{background:#dc2626;color:#fff}

/* ── EMPTY STATE ── */
.empty{text-align:center;padding:30px 16px;color:var(--tx3)}
.empty-ico{font-size:40px;margin-bottom:9px}
.empty-t{font-size:13.5px;font-weight:700}
.empty-s{font-size:12px;margin-top:4px}

/* ── ANIMATIONS ── */
@keyframes sin{from{opacity:0;transform:translateY(14px)}to{opacity:1;transform:translateY(0)}}
.ain{animation:sin .38s var(--ease) both}

/* Flatpickr */
.flatpickr-calendar{border-radius:16px!important;box-shadow:var(--sh-lg)!important;font-family:'Nunito',sans-serif!important}
.flatpickr-day.selected{background:var(--a1)!important;border-color:var(--a1)!important}
</style>
</head>
<body>
<div id="app">

<!-- HEADER -->
<div class="hdr">
<div class="hdr-inner">
    <div class="hdr-left">
        <div class="hdr-logo"><i class="fa-solid fa-stethoscope" style="color:#fff;font-size:18px;"></i></div>
        <div class="hdr-t">
            <h1 id="hdr-title">ICCBI Clinic</h1>
            <p id="hdr-sub">Smart School Health App</p>
        </div>
    </div>
    <div class="hdr-right">
        <button class="hdr-dm" id="hdrDarkBtn" onclick="toggleDark()" title="Toggle dark mode">🌙</button>
        <div class="hdr-badge"><span class="hdr-dot"></span>Online</div>
    </div>
</div>
</div>

<!-- CONTENT -->
<div id="content">
<div class="tv">

<!-- ══ HOME ══════════════════════════════════════════════ -->
<div id="tab-home" class="on">

    <div class="greeting">
        <div class="g-time" id="g-time">🌅 Good Morning</div>
        <div class="g-name" id="g-name">Welcome to SSCMS! 👋</div>
        <div class="g-sub">Your health companion at ICCBI — stay healthy, stay safe.</div>
    </div>

    <div class="qa-grid">
        <button class="qa" onclick="quickChat('Who are you, Nurse Angge?')">
            <i class="fa-solid fa-robot qa-ico"></i><span class="qa-lbl">Nurse Angge</span>
        </button>
        <button class="qa" onclick="goTab('appt')">
            <i class="fa-solid fa-calendar-plus qa-ico"></i><span class="qa-lbl">Book Appt</span>
        </button>
        <button class="qa" onclick="quickChat('What is the blood pressure normal range?')">
            <i class="fa-solid fa-heart-pulse qa-ico"></i><span class="qa-lbl">BP Info</span>
        </button>
        <button class="qa" onclick="quickChat('I feel stressed and anxious. Help me.')">
            <i class="fa-solid fa-brain qa-ico"></i><span class="qa-lbl">Mental Health</span>
        </button>
    </div>

    <div class="grid2">
        <div class="info-tile"><i class="fa-solid fa-clock it-ico"></i><span class="it-lbl">Clinic Hours</span><span class="it-val">8AM–5PM</span></div>
        <div class="info-tile"><i class="fa-solid fa-location-dot it-ico"></i><span class="it-lbl">Location</span><span class="it-val">Main Building</span></div>
        <div class="info-tile"><i class="fa-solid fa-user-doctor it-ico"></i><span class="it-lbl">Staff Today</span><span class="it-val">Nurse + Doctor</span></div>
        <div class="info-tile"><i class="fa-solid fa-phone it-ico"></i><span class="it-lbl">Emergency</span><span class="it-val">Call 911</span></div>
    </div>

    <div class="tip-card">
        <div class="tip-lbl">💡 Tip of the Day</div>
        <div class="tip-title" id="tip-t">Loading...</div>
        <div class="tip-body" id="tip-b"></div>
    </div>

    <div class="sec-hdr">
        <span class="sec-ttl">🇵🇭 Philippine Health News</span>
        <span class="sec-badge">LIVE</span>
    </div>

    <?php foreach ($news as $n): ?>
    <div class="news-card ain">
        <div class="news-ico"><?= $n['icon'] ?></div>
        <div class="news-c">
            <div class="news-tag"><?= htmlspecialchars($n['tag']) ?></div>
            <div class="news-ttl"><?= htmlspecialchars($n['title']) ?></div>
            <div class="news-sum"><?= htmlspecialchars($n['summary']) ?></div>
            <div class="news-meta"><?= htmlspecialchars($n['source']) ?> · <?= htmlspecialchars($n['date']) ?></div>
        </div>
    </div>
    <?php endforeach; ?>

    <div style="margin-top:16px;">
        <div class="sec-hdr"><span class="sec-ttl">✝️ Word for Today</span></div>
        <div class="gospel-card">
            <div class="gospel-ico">🕊️</div>
            <div class="gospel-v" id="gospel-v"></div>
            <div class="gospel-r" id="gospel-r"></div>
        </div>
    </div>

    <div style="height:8px;"></div>
</div>

<!-- ══ AI CHAT ════════════════════════════════════════════ -->
<div id="tab-ai">
    <div class="chat-mini-hdr">
        <div class="ai-av-sm">👩‍⚕️</div>
        <div class="ai-nm">
            <h3>Nurse Angge</h3>
            <p>AI Health Assistant · ICCBI Clinic</p>
        </div>
        <span class="online-badge">● Online 24/7</span>
    </div>

    <div class="chat-scroll" id="chat-msgs">
        <div class="msg-row ai ain">
            <div class="m-av">👩‍⚕️</div>
            <div>
                <div class="bubble ai"><strong>Kumusta! I'm Nurse Angge 👋</strong>

Your AI health companion at ICCBI Smart School Clinic. I can help with:

🩺 Health symptoms & first aid
💊 Medication guidance
🥗 Nutrition & wellness tips
😴 Sleep & mental health
📅 Appointment booking info
🚨 Emergency guidance

<em>Note: I provide general health information. For diagnosis, please visit the clinic!</em>

How can I help you today? 💙</div>
                <div class="m-time">Just now</div>
            </div>
        </div>
        <!-- typing indicator -->
        <div id="typing-r" class="msg-row ai" style="display:none;">
            <div class="m-av">👩‍⚕️</div>
            <div class="bubble ai">
                <div class="typing-wrap"><div class="td"></div><div class="td"></div><div class="td"></div></div>
            </div>
        </div>
    </div>

    <div class="chat-chips">
        <div class="chip-s" onclick="ss('I have fever and headache')">🌡️ Fever</div>
        <div class="chip-s" onclick="ss('I have a toothache')">🦷 Tooth</div>
        <div class="chip-s" onclick="ss('I feel stressed and anxious')">🧠 Stress</div>
        <div class="chip-s" onclick="ss('I have cough and colds')">🤧 Cough</div>
        <div class="chip-s" onclick="ss('I have stomach pain')">🫀 Stomach</div>
        <div class="chip-s" onclick="ss('I have a wound or cut')">🩹 Wound</div>
        <div class="chip-s" onclick="ss('I have trouble sleeping')">😴 Sleep</div>
        <div class="chip-s" onclick="ss('Give me nutrition tips')">🥗 Nutrition</div>
        <div class="chip-s" onclick="ss('Tell me about HIV in Philippines')">🔴 HIV</div>
        <div class="chip-s" onclick="ss('I have menstrual cramps')">🌸 Period</div>
    </div>

    <div class="chat-inp-area">
        <textarea id="chat-input" placeholder="Ask Nurse Angge anything..." rows="1"></textarea>
        <button class="send-btn" onclick="sendMsg()" id="send-btn">
            <i class="fa-solid fa-paper-plane" style="color:#fff;font-size:16px;"></i>
        </button>
    </div>
</div>

<!-- ══ APPOINTMENTS ════════════════════════════════════════ -->
<div id="tab-appt">

    <div class="appt-hero">
        <h2>Book an Appointment</h2>
        <p>Schedule your clinic visit in seconds.</p>
    </div>

    <form id="apptForm" autocomplete="off">
        <input type="hidden" name="csrf_token" id="f-csrf" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

        <div class="fg2">
            <div class="fg">
                <label class="fl">Full Name <span class="req">*</span></label>
                <input type="text" name="patient_name" id="f-n" class="fc" placeholder="Juan Dela Cruz" required>
            </div>
            <div class="fg">
                <label class="fl">Category <span class="req">*</span></label>
                <select name="category" id="f-c" class="fc" required>
                    <option value="">Select...</option>
                    <optgroup label="Students">
                        <option value="Pre School">Pre School</option>
                        <option value="Elementary">Elementary</option>
                        <option value="JHS">JHS</option>
                        <option value="SHS">SHS</option>
                        <option value="College">College</option>
                        <option value="Graduate School">Graduate School</option>
                        <option value="Alumni">Alumni</option>
                    </optgroup>
                    <optgroup label="Staff">
                        <option value="Faculty">Faculty</option>
                        <option value="Faculty Staff">Faculty Staff</option>
                        <option value="Administrative Staff">Administrative Staff</option>
                        <option value="Support Staff">Support Staff</option>
                        <option value="Clinic Staff">Clinic Staff</option>
                        <option value="Maintenance Staff">Maintenance Staff</option>
                        <option value="Janitorial Staff">Janitorial Staff</option>
                        <option value="Security Staff">Security Staff</option>
                        <option value="Non-Teaching Staff">Non-Teaching Staff</option>
                    </optgroup>
                    <optgroup label="Other">
                        <option value="Non-Student">Non-Student</option>
                        <option value="Visitor">Visitor</option>
                        <option value="Other">Other</option>
                    </optgroup>
                </select>
            </div>
        </div>

        <div class="fg2">
            <div class="fg">
                <label class="fl">Phone <span class="req">*</span></label>
                <input type="tel" name="phone" id="f-p" class="fc" placeholder="09XXXXXXXXX" pattern="[0-9]{10,11}">
            </div>
            <div class="fg">
                <label class="fl">Appoint With <span class="req">*</span></label>
                <select name="appointee" id="f-ap" class="fc" required>
                    <option value="">Select...</option>
                    <option value="Doctor">👨‍⚕️ Doctor</option>
                    <option value="Nurse">👩‍⚕️ Nurse</option>
                    <option value="Dentist">🦷 Dentist</option>
                </select>
            </div>
        </div>

        <div class="fg2">
            <div class="fg">
                <label class="fl">Date <span class="req">*</span></label>
                <input type="text" name="appointment_date" id="f-d" class="fc" placeholder="Pick date" readonly required>
            </div>
            <div class="fg">
                <label class="fl">Time <span class="req">*</span></label>
                <select name="appointment_time" id="f-t" class="fc" required>
                    <option value="">Select...</option>
                    <?php for($h=7;$h<=16;$h++): $t=sprintf("%02d:00:00",$h); ?>
                    <option value="<?=$t?>"><?=date("g:i A",strtotime($t))?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>

        <div class="fg">
            <label class="fl">Reason for Visit <span class="req">*</span></label>
            <div class="chips-r" id="r-chips">
                <?php
                $chips=['General Check-up','Fever','Headache','Stomach Pain','Cough & Colds','Wound Care','Toothache','Eye Irritation','Allergy','Dizziness','Blood Pressure','Medical Certificate','Follow-up','Menstrual Pain','Vaccination'];
                foreach($chips as $cp): ?>
                <div class="chip-r" onclick="rc(this,'<?=htmlspecialchars($cp,ENT_QUOTES)?>')"><?=htmlspecialchars($cp)?></div>
                <?php endforeach; ?>
            </div>
            <textarea name="reason" id="f-r" class="fc" rows="3" placeholder="Describe your symptoms..." required></textarea>
        </div>

        <button type="submit" class="btn-sub" id="appt-btn"><i class="fa-solid fa-calendar-check"></i> Submit Appointment</button>
    </form>

    <div style="margin-top:20px;">
        <div class="sec-hdr">
            <span class="sec-ttl">📋 Recent Appointments</span>
            <span style="font-size:11px;color:var(--a1);font-weight:800;cursor:pointer;" onclick="loadAppts()">Refresh ↻</span>
        </div>
        <div id="appt-list">
            <div class="empty"><div class="empty-ico">📅</div><div class="empty-t">No appointments yet</div><div class="empty-s">Book your first appointment above!</div></div>
        </div>
    </div>
    <div style="height:20px;"></div>
</div>


</div><!-- /tv -->
</div><!-- /content -->

<!-- BOTTOM NAV -->
<div class="bnav">
    <div class="nav-ind" id="nav-ind"></div>
    <button class="nbtn on" id="nb-home" onclick="goTab('home')">
        <i class="fa-solid fa-house"></i>
        <span>Home</span>
    </button>
    <button class="nbtn" id="nb-ai" onclick="goTab('ai')">
        <i class="fa-solid fa-comment-medical"></i>
        <span>Nurse Angge</span>
    </button>
    <button class="nbtn" id="nb-appt" onclick="goTab('appt')">
        <i class="fa-solid fa-calendar-check"></i>
        <span>Appointments</span>
    </button>
</div>

</div><!-- /app -->

<!-- SUCCESS MODAL -->
<div class="modal-ol" id="m-success">
    <div class="modal-sh">
        <div class="mh"></div>
        <div class="m-ico-w" style="background:#dcfce7;">✅</div>
        <div class="m-ttl">Appointment Booked!</div>
        <div class="m-txt">Your appointment request has been submitted. You'll receive an <strong>SMS notification</strong> once the clinic confirms it. Thank you!</div>
        <button class="m-btn" onclick="cm('m-success')">Got it — thanks! 🎉</button>
    </div>
</div>

<!-- TOAST -->
<div id="toast"><span id="t-ico">✅</span><span id="t-msg">Saved!</span></div>

<script>
// ─── STATE ─────────────────────────────────────────────────
const A = {
    tab: 'home',
    sending: false,
};

const TABS = ['home','ai','appt'];
const HDRS = {home:['ICCBI Clinic','Smart School Health App'],ai:['Nurse Angge AI','Your AI Health Companion'],appt:['Appointments','Book Your Clinic Visit']};

// ─── TAB SWITCH ────────────────────────────────────────────
function goTab(t) {
    document.querySelectorAll('.tv>div').forEach(v=>v.classList.remove('on'));
    document.querySelectorAll('.nbtn').forEach(b=>b.classList.remove('on'));
    document.getElementById('tab-'+t).classList.add('on');
    document.getElementById('nb-'+t).classList.add('on');
    document.getElementById('nav-ind').style.left = (TABS.indexOf(t)*33.333)+'%';
    document.getElementById('hdr-title').textContent = HDRS[t][0];
    document.getElementById('hdr-sub').textContent = HDRS[t][1];
    A.tab = t;
    document.getElementById('content').scrollTop = 0;
    if(t==='appt') { loadAppts(); autofillAppt(); }
}

// ─── HOME INIT ─────────────────────────────────────────────
const TIPS=[
    {t:'Stay Hydrated! 💧',b:'Drink at least 8 glasses of water daily. Proper hydration boosts energy, improves focus, and keeps your immune system strong.'},
    {t:'Wash Your Hands 🧼',b:'Wash hands for at least 20 seconds with soap — before meals, after coughing, and after using the restroom. It prevents up to 80% of infections!'},
    {t:'Sleep 7–9 Hours 😴',b:'Quality sleep repairs your body, consolidates memory, and strengthens immunity. A consistent sleep schedule is the foundation of good health.'},
    {t:'Eat Vegetables Daily 🥗',b:'Include local veggies like kangkong, malunggay, and ampalaya in your meals — affordable and packed with essential nutrients!'},
    {t:'Manage Stress 🧘',b:'Practice deep breathing: inhale for 4 seconds, hold for 4, exhale for 6 seconds. Do this 5 times whenever you feel overwhelmed.'},
    {t:'Get Moving! 🚶',b:'Even a 10-minute walk between classes improves circulation, boosts mood, and reduces fatigue. Your body and mind will thank you!'},
    {t:'Protect Your Eyes 👁️',b:'Follow the 20-20-20 rule: every 20 minutes, look at something 20 feet away for 20 seconds. This reduces eye strain from screen use.'},
    {t:'Eat Breakfast 🍳',b:'Never skip breakfast! It powers your brain for better focus, learning, and memory retention throughout the day.'},
];
const GOSPELS=[
    {v:'"Do not be anxious about anything, but in every situation, by prayer and petition, with thanksgiving, present your requests to God."',r:'Philippians 4:6'},
    {v:'"The Lord is my shepherd, I lack nothing. He makes me lie down in green pastures, he leads me beside quiet waters, he refreshes my soul."',r:'Psalm 23:1-3'},
    {v:'"Come to me, all you who are weary and burdened, and I will give you rest."',r:'Matthew 11:28'},
    {v:'"He heals the brokenhearted and binds up their wounds."',r:'Psalm 147:3'},
    {v:'"For I know the plans I have for you," declares the LORD, "plans to prosper you and not to harm you, plans to give you hope and a future."',r:'Jeremiah 29:11'},
    {v:'"I can do all this through him who gives me strength."',r:'Philippians 4:13'},
    {v:'"So do not fear, for I am with you; do not be dismayed, for I am your God. I will strengthen you and help you."',r:'Isaiah 41:10'},
];

function initHome(){
    const h=new Date().getHours();
    const g=h<12?'🌅 Good Morning':h<18?'☀️ Good Afternoon':'🌙 Good Evening';
    document.getElementById('g-time').textContent=g;
    document.getElementById('g-name').textContent='Welcome to SSCMS! 👋';

    const tip=TIPS[new Date().getDay()%TIPS.length];
    document.getElementById('tip-t').textContent=tip.t;
    document.getElementById('tip-b').textContent=tip.b;

    const gos=GOSPELS[new Date().getDate()%GOSPELS.length];
    document.getElementById('gospel-v').textContent=gos.v;
    document.getElementById('gospel-r').textContent='— '+gos.r;
}

// ─── CHAT ──────────────────────────────────────────────────
function sendMsg(){
    const inp=document.getElementById('chat-input');
    const msg=inp.value.trim();
    if(!msg||A.sending)return;
    inp.value=''; inp.style.height='auto';
    doSend(msg);
}

function ss(text){ goTab('ai'); setTimeout(()=>doSend(text),100); }
function quickChat(text){ goTab('ai'); setTimeout(()=>doSend(text),200); }

function autofillAppt(){}

function doSend(msg){
    if(!msg.trim()||A.sending)return;
    A.sending=true;
    const c=document.getElementById('chat-msgs');
    const now=new Date().toLocaleTimeString('en-PH',{hour:'2-digit',minute:'2-digit'});

    const ur=document.createElement('div');
    ur.className='msg-row user ain';
    ur.innerHTML=`<div class="m-av u">🧑</div><div><div class="bubble user">${esc(msg)}</div><div class="m-time" style="text-align:right">${now}</div></div>`;
    c.appendChild(ur);

    const tr=document.getElementById('typing-r');
    tr.style.display='flex';
    scrollChat();

    setTimeout(()=>{
        $.ajax({
            url:'?ajax=1',
            method:'POST',
            data:{action:'ai_chat',message:msg},
            dataType:'json',
            success(res){
                tr.style.display='none'; A.sending=false;
                if(res&&res.reply) addAI(res.reply, res.timestamp||now);
            },
            error(){
                tr.style.display='none'; A.sending=false;
                addAI("I'm sorry, there was a connection issue. Please try again! 🔄", now);
            }
        });
    }, 900+Math.random()*500);
}

function addAI(text, time){
    const c=document.getElementById('chat-msgs');
    const row=document.createElement('div');
    row.className='msg-row ai ain';
    const fmt=text.replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>').replace(/\*(.*?)\*/g,'<em>$1</em>');
    row.innerHTML=`<div class="m-av">👩‍⚕️</div><div><div class="bubble ai">${fmt}</div><div class="m-time">${time}</div></div>`;
    c.appendChild(row);
    scrollChat();
}

function scrollChat(){
    const c=document.getElementById('chat-msgs');
    c.scrollTo({top:c.scrollHeight,behavior:'smooth'});
}

function clearChat(){
    document.getElementById('chat-msgs').innerHTML=`
    <div class="msg-row ai ain"><div class="m-av">👩‍⚕️</div><div>
    <div class="bubble ai">Chat cleared! I'm Nurse Angge — ready to help again. 💙 What can I assist you with?</div>
    <div class="m-time">Just now</div></div></div>`;
    toast('✅ Chat cleared!','success');
}

function esc(t){return t.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/\n/g,'<br>')}

// ─── APPOINTMENTS ──────────────────────────────────────────
function rc(el,v){
    el.classList.toggle('sel');
    const sel=[...document.querySelectorAll('.chip-r.sel')].map(c=>c.textContent.trim());
    document.getElementById('f-r').value=sel.join('; ');
}

// ─── APPOINTMENTS ──────────────────────────────────────────

function loadAppts(){
    const c=document.getElementById('appt-list');
    c.innerHTML='<div class="empty"><div class="empty-ico">⏳</div><div class="empty-t">Loading...</div></div>';
    $.ajax({
        url:'?ajax=1',method:'POST',data:{action:'get_appointments'},dataType:'json',
        success(res){
            if(!res.success||!res.data||!res.data.length){
                c.innerHTML='<div class="empty"><div class="empty-ico">📅</div><div class="empty-t">No appointments yet</div><div class="empty-s">Book your first appointment above!</div></div>';
                return;
            }
            let h='';
            res.data.forEach(a=>{
                const s=(a.status||'pending').toLowerCase();
                const sc={pending:'p',approved:'a',cancelled:'c',completed:'d',rejected:'c'}[s]||'p';
                const bc={pending:'b-p',approved:'b-a',cancelled:'b-c',completed:'b-d',rejected:'b-c'}[s]||'b-p';
                const rawDate=a.appointment_date||'';
                const d=rawDate?new Date(rawDate+'T00:00:00').toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'}):'—';
                const t=fmtT(a.appointment_time);
                const statusLabel=a.status?a.status.charAt(0).toUpperCase()+a.status.slice(1).toLowerCase():'Pending';
                h+=`<div class="appt-item ain">
                      <div class="sd s-${sc}"></div>
                      <div class="ai-info2">
                        <div class="an">${esc2(a.patient_name||'—')}</div>
                        <div class="ad">${esc2(a.appointee||'')} · ${d}${t?' at '+t:''}</div>
                        <div class="ad" style="color:var(--tx3)">${esc2((a.reason||'').substring(0,55))}${(a.reason||'').length>55?'…':''}</div>
                      </div>
                      <span class="sbadge ${bc}">${statusLabel}</span>
                    </div>`;
            });
            c.innerHTML=h;
        },
        error(){ c.innerHTML='<div class="empty"><div class="empty-ico">⚠️</div><div class="empty-t">Failed to load</div><div class="empty-s">Check your connection and try again.</div></div>'; }
    });
}

function fmtT(t){
    if(!t)return'';
    const parts=t.split(':');
    const hr=parseInt(parts[0]);
    const min=parts[1]||'00';
    return(hr%12||12)+':'+min+' '+(hr>=12?'PM':'AM');
}

function esc2(t){return(t+'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;')}

$('#apptForm').on('submit',function(e){
    e.preventDefault();

    // Client-side validation
    const flds=[
        {id:'f-n',  label:'Full Name'},
        {id:'f-c',  label:'Category'},
        {id:'f-p',  label:'Phone'},
        {id:'f-ap', label:'Appointee'},
        {id:'f-d',  label:'Date'},
        {id:'f-t',  label:'Time'},
        {id:'f-r',  label:'Reason'},
    ];
    let ok=true;
    flds.forEach(({id})=>{
        const el=document.getElementById(id);
        if(!el||!el.value.trim()){if(el)el.classList.add('err');ok=false;}
        else if(el) el.classList.remove('err');
    });
    if(!ok){toast('⚠️ Please fill in all required fields','error');return;}

    const ph=document.getElementById('f-p').value.trim();
    if(!/^\d{10,11}$/.test(ph)){
        document.getElementById('f-p').classList.add('err');
        toast('⚠️ Phone must be 10–11 digits (numbers only)','error');
        return;
    }

    const btn=document.getElementById('appt-btn');
    btn.disabled=true;
    btn.innerHTML='<i class="fa-solid fa-spinner fa-spin"></i> Submitting...';

    // Build FormData and POST to the shared submit_appointment.php
    const fd=new FormData(this);

    $.ajax({
        url:'/appointments/submit_appointment.php',
        method:'POST',
        data:fd,
        processData:false,
        contentType:false,
        dataType:'json',
        success(res){
            btn.disabled=false;
            btn.innerHTML='<i class="fa-solid fa-calendar-check"></i> Submit Appointment';
            if(res.success){
                // Reset form
                document.getElementById('apptForm').reset();
                document.querySelectorAll('.chip-r.sel').forEach(c=>c.classList.remove('sel'));
                const fp=document.getElementById('f-d')._flatpickr;
                if(fp)fp.clear();
                // Refresh CSRF token from server response (submit_appointment regenerates it)
                // Re-fetch a fresh token via a quick AJAX call
                refreshCsrf();
                om('m-success');
                loadAppts();
            } else {
                toast('❌ '+(res.message||'Failed to book. Please try again.'),'error');
            }
        },
        error(xhr){
            btn.disabled=false;
            btn.innerHTML='<i class="fa-solid fa-calendar-check"></i> Submit Appointment';
            let msg='❌ Server error. Please try again.';
            try{
                const r=JSON.parse(xhr.responseText);
                if(r.message) msg='❌ '+r.message;
            }catch(e){}
            toast(msg,'error');
        }
    });
});

// Re-fetch a fresh CSRF token after successful submission
function refreshCsrf(){
    $.ajax({
        url:'?ajax=1',method:'POST',data:{action:'get_csrf'},dataType:'json',
        success(res){
            if(res.token) document.getElementById('f-csrf').value=res.token;
        }
    });
}

// ─── DARK MODE TOGGLE ──────────────────────────────────────
function toggleDark(){
    const html=document.documentElement;
    const isDark=html.getAttribute('data-theme')==='dark';
    const next=!isDark;
    html.setAttribute('data-theme',next?'dark':'light');
    localStorage.setItem('sscms_theme',next?'dark':'light');
    document.getElementById('hdrDarkBtn').textContent=next?'☀️':'🌙';
}

// ─── MODALS ────────────────────────────────────────────────
function om(id){document.getElementById(id).classList.add('open')}
function cm(id){document.getElementById(id).classList.remove('open')}
document.querySelectorAll('.modal-ol').forEach(m=>m.addEventListener('click',function(e){if(e.target===this)cm(this.id)}));

// ─── TOAST ─────────────────────────────────────────────────
let tt;
function toast(msg,type='info'){
    clearTimeout(tt);
    const el=document.getElementById('toast');
    document.getElementById('t-msg').textContent=msg;
    el.className='show '+type;
    tt=setTimeout(()=>{el.className=type;},3000);
}

// ─── CHAT INPUT AUTO RESIZE ────────────────────────────────
const ci=document.getElementById('chat-input');
ci.addEventListener('input',function(){this.style.height='auto';this.style.height=Math.min(this.scrollHeight,90)+'px';});
ci.addEventListener('keypress',function(e){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendMsg();}});
document.querySelectorAll('.fc').forEach(el=>{
    el.addEventListener('focus',()=>el.classList.remove('err'));
    el.addEventListener('change',()=>el.classList.remove('err'));
});

// ─── INIT ──────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded',function(){
    flatpickr('#f-d',{
        dateFormat:'Y-m-d',minDate:'today',
        maxDate:new Date(new Date().setDate(new Date().getDate()+30)),
        disable:[d=>d.getDay()===0||d.getDay()===6],
        disableMobile:false,
    });

    // Apply saved dark mode
    const isDark=localStorage.getItem('sscms_theme')==='dark';
    document.documentElement.setAttribute('data-theme',isDark?'dark':'light');
    document.getElementById('hdrDarkBtn').textContent=isDark?'☀️':'🌙';

    initHome();
    document.getElementById('nav-ind').style.left='0%';
    scrollChat();
    setInterval(initHome,60000);
    console.log('✅ SSCMS Nurse Angge v2.0 ready');
});
</script>
</body>
</html>