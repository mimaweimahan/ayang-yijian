<?php

namespace app\api\controller;

use app\common\model\AppRoll;
use app\common\model\Configure;
use app\common\model\Level;
use app\common\model\Message;
use app\common\model\User;
use app\common\model\WalletLog;
use app\common\model\Announcement;
use think\facade\Cache;
use think\facade\Db;
use think\facade\Log;
use app\common\model\Goods;

class Index extends Base
{
    //登录
    public function login()
    {
        try {
            file_put_contents('/tmp/debug_login.log', "== login() 请求开始 ==\n", FILE_APPEND);

            $username = $this->request->post('username', '', 'trim');
            $password = $this->request->post('pass', '', 'trim');
            $lang = $this->request->post('lang', 'en', 'trim');

            file_put_contents('/tmp/debug_login.log', "收到参数：username={$username}, password={$password}, lang={$lang}\n", FILE_APPEND);

            if (!$username) {
                $this->result(false, '参数有误');
            }

            $login_is_binary = $this->config['system_basic']['login_binary'] ?? 0; //登录账号是否区别大小写
            $binary = '';
            if($login_is_binary == 1){
                $binary = 'binary';
            }

            file_put_contents('/tmp/debug_login.log', "login_is_binary={$login_is_binary} , 使用查询模式：{$binary}\n", FILE_APPEND);

            // 使用参数化查询，防SQL注入
            $info = User::whereRaw("$binary username = ?", [$username])
                ->field('id,username,password,nickname,status,invite_code')
                ->find();

            if (!$info) {
                file_put_contents('/tmp/debug_login.log', "❌ 用户不存在\n", FILE_APPEND);
                if($lang == 'zh')$lang = '用户名或者密码错误';//中文
                if($lang == 'pt')$lang = 'Nome de utilizador ou palavra-passe incorretos';//葡萄牙语
                if($lang == 'nl')$lang = 'Verkeerde gebruikersnaam of wachtwoord';//荷兰语
                if($lang == 'he')$lang = 'שם משתמש או סיסמה שגויים';//希伯来语
                if($lang == 'fr')$lang = 'Nom d utilisateur ou mot de passe incorrect';//法语
                if($lang == 'es')$lang = 'Por favor, complete primero la información de retiro';//西班牙语
                if($lang == 'en')$lang = 'Wrong username or password';//英语
                if($lang == 'de')$lang = 'Falscher Benutzername oder falsches Passwort';//德语
                if($lang == 'ar')$lang = 'ياسم المستخدم أو كلمة المرور خاطئة';//阿拉伯语
                $this->result(false, $lang);
            }

            $info = $info->toArray(); // ⚠️ 必须 toArray，避免缓存时报错

            file_put_contents('/tmp/debug_login.log', "✅ 用户数据：" . json_encode($info, JSON_UNESCAPED_UNICODE) . "\n", FILE_APPEND);

            if ($password != $info['password']) {
                file_put_contents('/tmp/debug_login.log', "❌ 密码错误\n", FILE_APPEND);
                if($lang == 'zh')$lang = '用户名或者密码错误';//中文
                if($lang == 'pt')$lang = 'Nome de utilizador ou palavra-passe incorretos';//葡萄牙语
                if($lang == 'nl')$lang = 'Verkeerde gebruikersnaam of wachtwoord';//荷兰语
                if($lang == 'he')$lang = 'שם משתמש או סיסמה שגויים';//希伯来语
                if($lang == 'fr')$lang = 'Nom d utilisateur ou mot de passe incorrect';//法语
                if($lang == 'es')$lang = 'Por favor, complete primero la información de retiro';//西班牙语
                if($lang == 'en')$lang = 'Wrong username or password';//英语
                if($lang == 'de')$lang = 'Falscher Benutzername oder falsches Passwort';//德语
                if($lang == 'ar')$lang = 'ياسم المستخدم أو كلمة المرور خاطئة';//阿拉伯语
                $this->result(false, $lang);
            }

            if ($info['status'] == 2) {
                file_put_contents('/tmp/debug_login.log', "❌ 账号被禁用\n", FILE_APPEND);
                if($lang == 'zh')$lang = '该账号被禁用了，请联系客服';//中文
                if($lang == 'pt')$lang = 'Esta conta foi desativada, contacte o serviço de apoio ao cliente';//葡萄牙语
                if($lang == 'nl')$lang = 'Dit account is uitgeschakeld, neem contact op met de klantenservice';//荷兰语
                if($lang == 'he')$lang = 'אחשבון זה הושבת, אנא צור קשר עם שירות הלקוחות';//希伯来语
                if($lang == 'fr')$lang = 'Ce compte a été désactivé, veuillez contacter le service client';//法语
                if($lang == 'es')$lang = 'Esta cuenta ha sido deshabilitada, por favor contacte con atención al cliente';//西班牙语
                if($lang == 'en')$lang = 'This account has been disabled, please contact customer service';//英语
                if($lang == 'de')$lang = 'Dieses Konto wurde deaktiviert. Bitte wenden Sie sich an den Kundendienst.';//德语
                if($lang == 'ar')$lang = 'يتم تعطيل هذا الحساب، يرجى الاتصال بخدمة العملاء';//阿拉伯语
                $this->result(false, $lang);
            }

            $ip = $this->request->ip();
            if (strlen($ip) > 39) $ip = substr($ip, 0, 39); // 防止写入过长 IP

            $data['login_time'] = time();
            $data['login_ip'] = $ip;

            file_put_contents('/tmp/debug_login.log', "准备更新用户登录信息：" . json_encode($data) . "\n", FILE_APPEND);

            User::update($data, ['id' => $info['id']]);
            file_put_contents('/tmp/debug_login.log', "✅ 更新成功\n", FILE_APPEND);

            $auth = md5($info['password'] . $info['id']) . '!' . $info['username'];
            $info['auth'] = $auth;

            file_put_contents('/tmp/debug_login.log', "尝试写入 Cache：{$auth}\n", FILE_APPEND);
            try {
                Cache::set($auth, $info, 0);
                file_put_contents('/tmp/debug_login.log', "✅ Cache 设置成功\n", FILE_APPEND);
            } catch (\Throwable $ce) {
                file_put_contents('/tmp/debug_login.log', "❌ Cache 写入失败：" . $ce->getMessage() . "\n", FILE_APPEND);
            }

            file_put_contents('/tmp/debug_login.log', "== login 成功 == 用户ID:{$info['id']}\n", FILE_APPEND);

            // 保持原方法的返回格式
            // 保持原方法的返回格式
            return json([
                'success' => true,
                'msg' => '操作成功',
                'data' => [
                    'authorization' => $auth
                ]
            ]);
        } catch (\Throwable $e) {
            file_put_contents('/tmp/debug_login.log', "== login 异常 ==\n" . $e->getMessage() . "\n", FILE_APPEND);
            $this->result(false, '服务器错误');
        }
    }
    //注册
    public function doRegister()
    {
        $nickname=$username = $this->request->post('username', '', 'trim');
        $password = $this->request->post('pass', '', 'trim');
        $deal_pass = $this->request->post('deal_pass', '', 'trim');
        $invite_code = $this->request->post('invite_code', '', 'trim');
        // $nickname = $this->request->post('nickname', '', 'trim');
        $langs = $this->request->post('langs', '', 'trim');
        if (!$username || !$password || !$deal_pass){
				if($langs == 'zh'){//中文
					$langs = '參數有誤';
				}
				if($langs == 'pt'){//葡萄牙语
					$langs = 'Parâmetro errado';
				}
				if($langs == 'nl'){//荷兰语
					$langs = 'Verkeerde parameter';
				}
				if($langs == 'he'){//希伯来语
					$langs = 'פרמטרים שגויים';
				}
				if($langs == 'fr'){//法语
					$langs = 'Mauvais paramètre';
				}
				if($langs == 'es'){//西班牙语
					$langs = 'Parámetros incorrectos';
				}
				if($langs == 'en'){//英语
					$langs = 'Incorrect parameters';
				}
				if($langs == 'de'){//德语
					$langs = 'Falsche Parameter';
				}
				if($langs == 'ar'){//阿拉伯语
					$langs = 'Falsche Parameter';
				}
            $this->result(false, $langs);
		}
        Db::startTrans();
        try {
            $mod = new User();
            $info = $mod->where(['invite_code' => $invite_code])->field('id,status,sub_num,family,agent_id')->find();
			if($info['agent_id'] == 0){
				$info['agent_id'] = $info['id'];
			}
            if (!$info){
				if($langs == 'zh'){//中文
					$langs = '邀請碼無效';
				}
				if($langs == 'pt'){//葡萄牙语
					$langs = 'Código de convite inválido';
				}
				if($langs == 'nl'){//荷兰语
					$langs = 'Ongeldige uitnodigingscode';
				}
				if($langs == 'he'){//希伯来语
					$langs = 'קוד הזמנה לא חוקי';
				}
				if($langs == 'fr'){//法语
					$langs = 'Code d invitation invalide';
				}
				if($langs == 'es'){//西班牙语
					$langs = 'Código de invitación no válido';
				}
				if($langs == 'en'){//英语
					$langs = 'Invalid invitation code';
				}
				if($langs == 'de'){//德语
					$langs = 'Ungültiger Einladungscode';
				}
				if($langs == 'ar'){//阿拉伯语
					$langs = 'Invalid invitation code';
				}
                throw new \Exception($langs);
				
				}
            if ($info['status'] == 2){
				throw new \Exception('上级被禁用');
			}

                
            $count = $mod->where(['username' => $username])->count();
            if ($count){
					if($langs == 'zh'){//中文
						$langs = '使用者名稱已存在';
					}
					if($langs == 'pt'){//葡萄牙语
						$langs = 'O nome de usuário já existe';
					}
					if($langs == 'nl'){//荷兰语
						$langs = 'Gebruikersnaam bestaat al';
					}
					if($langs == 'he'){//希伯来语
						$langs = 'שם משתמש כבר קיים';
					}
					if($langs == 'fr'){//法语
						$langs = 'Le nom d utilisateur existe déjà';
					}
					if($langs == 'es'){//西班牙语
						$langs = 'El nombre de usuario ya existe';
					}
					if($langs == 'en'){//英语
						$langs = 'Username already exists';
					}
					if($langs == 'de'){//德语
						$langs = 'Benutzername existiert bereits';
					}
					if($langs == 'ar'){//阿拉伯语
						$langs = 'اسم المستخدم موجود بالفعل';
					}

                throw new \Exception($langs);
				}
            $data['username'] = $username;
            $data['password'] = $password;
            $data['deal_pass'] = $deal_pass;
            $data['pid'] = $info['id'];
            $data['agent_id'] = $info['agent_id'];
            $data['nickname'] = $nickname;
            // $data['email'] = $email;
            $data['balance'] = $this->config['system_basic']['register_reward'] ?? 0;//注册奖励
            $data['invite_code'] = getRandStr(6);
            $data['create_time'] = time();
            $data['credit_score'] = 1000;
            if ($info['family']) {
                $family = explode('-', $info['family']);
                if (count($family) == 5) {
                    unset($family[4]);
                }
                array_unshift($family, $info['id']);
                $data['family'] = implode('-', $family);
            } else {
                $data['family'] = $info['id'];
            }
			$mes_data['title_zh'] = $this->config['system_home']['register_title_zh'] ?? '';
            $mes_data['title_en'] = $this->config['system_home']['register_title_en'] ?? '';
			
			$mes_data['title_he'] = $this->config['system_home']['register_title_he'] ?? '';
			$mes_data['title_ar'] = $this->config['system_home']['register_title_ar'] ?? '';
			$mes_data['title_es'] = $this->config['system_home']['register_title_es'] ?? '';
			$mes_data['title_pt'] = $this->config['system_home']['register_title_pt'] ?? '';
			$mes_data['title_nl'] = $this->config['system_home']['register_title_nl'] ?? '';
			$mes_data['title_fr'] = $this->config['system_home']['register_title_fr'] ?? '';
			$mes_data['title_de'] = $this->config['system_home']['register_title_de'] ?? '';
            
            $mes_data['content_en'] = $this->config['system_home']['register_en'] ?? '';
            $mes_data['content_zh'] = $this->config['system_home']['register_zh'] ?? '';
			
			$mes_data['content_he'] = $this->config['system_home']['register_he'] ?? '';
			$mes_data['content_ar'] = $this->config['system_home']['register_ar'] ?? '';
			$mes_data['content_es'] = $this->config['system_home']['register_es'] ?? '';
			$mes_data['content_pt'] = $this->config['system_home']['register_pt'] ?? '';
			$mes_data['content_nl'] = $this->config['system_home']['register_nl'] ?? '';
			$mes_data['content_fr'] = $this->config['system_home']['register_fr'] ?? '';
			$mes_data['content_de'] = $this->config['system_home']['register_de'] ?? '';
			
            $mes_data['create_time'] = time();
            $mod->update(['sub_num' => $info['sub_num'] + 1], ['id' => $info['id']]);
            $res = $mod->insertGetId($data);
            $mes_data['uid'] = $res;
            Message::create($mes_data);
            Db::commit();
        } catch (\Exception $e) {
            if ($e->getCode() == 0) {
                $this->result(false, $e->getMessage());
            } else {
                Db::rollback();
                Log::error('添加用户失败-' . $e->getMessage() . ':' . $e->getLine());
                $this->result(false, '系统繁忙，请重试');
            }
        }
        $this->result(true, '注册成功');
    }

    //退出
    public function logout()
    {
        $auth = $this->request->user['auth'] ?? '';
        Cache::delete($auth);
        $this->result(true, '退出成功');
    }

    //获取文章数据
    public function getArticle()
    {
        $lang = $this->request->get('lang','');
        $data = [
            'announcement' => $this->config['system_home']['announcement_center_'.$lang.''] ?? '',
            'help' => $this->config['system_home']['help_'.$lang.''] ?? '',
            'about_us' => $this->config['system_home']['about_us_'.$lang.''] ?? '',
            'services' => $this->config['system_home']['our_services_'.$lang.''] ?? '',
        ];
        $this->result(true, '操作成功', $data);
    }
    
     //获取商品数据
    public function goodsList()
    {   
        $page = $this->request->post('page', 1, 'intval');
        $data =(new Goods)->page($page, 20)->where('see',1)->order('id', 'desc')->select()->toArray();
       
        foreach ($data as $key=>$val){
            $data[$key]['img']='//ceshi.slkekgnseee.top'.$val['img'];
        }
        $this->result(true, '操作成功', $data);
    }
    
     //公告
    public function noticeList()
    {   
        $page = $this->request->post('page', 1, 'intval');
        $data =(new Announcement)->page($page, 10)->order('id desc')->select()->toArray();
        $this->result(true, '操作成功', $data);
    }


    //获取首页数据
    public function getIndex()
    {   
        
        // echo '<pre>';var_dump($_SERVER);die;
        $domain = $this->request->domain();
        $uid = $this->request->user['id'] ?? 0;
        if ($uid) {
            $data['no_read_mes'] = Message::where(['uid' => $uid, 'status' => 0])->count();
        }

        
        
        $cert_list_en = explode(',', $this->config['system_home']['cert_list_en'] ?? '');
        $cert_list_zh = explode(',', $this->config['system_home']['cert_list_zh'] ?? '');
		
		$cert_list_he = explode(',', $this->config['system_home']['cert_list_he'] ?? '');
		$cert_list_ar = explode(',', $this->config['system_home']['cert_list_ar'] ?? '');
		$cert_list_es = explode(',', $this->config['system_home']['cert_list_es'] ?? '');
		$cert_list_pt = explode(',', $this->config['system_home']['cert_list_pt'] ?? '');
		$cert_list_nl = explode(',', $this->config['system_home']['cert_list_nl'] ?? '');
		$cert_list_fr = explode(',', $this->config['system_home']['cert_list_fr'] ?? '');
		$cert_list_de = explode(',', $this->config['system_home']['cert_list_de'] ?? '');
		
        
        
        $event_list_en = explode(',', $this->config['system_home']['event_list_en'] ?? '');
        $event_list_zh = explode(',', $this->config['system_home']['event_list_zh'] ?? '');
		
		$event_list_he = explode(',', $this->config['system_home']['event_list_he'] ?? '');
		$event_list_ar = explode(',', $this->config['system_home']['event_list_ar'] ?? '');
		$event_list_es = explode(',', $this->config['system_home']['event_list_es'] ?? '');
		$event_list_pt = explode(',', $this->config['system_home']['event_list_pt'] ?? '');
		$event_list_nl = explode(',', $this->config['system_home']['event_list_nl'] ?? '');
		$event_list_fr = explode(',', $this->config['system_home']['event_list_fr'] ?? '');
		$event_list_de = explode(',', $this->config['system_home']['event_list_de'] ?? '');
        
        
        array_shift($cert_list_en);
        array_shift($cert_list_zh);
		
		array_shift($cert_list_he);
		array_shift($cert_list_ar);
		array_shift($cert_list_es);
		array_shift($cert_list_pt);
		array_shift($cert_list_nl);
		array_shift($cert_list_fr);
		array_shift($cert_list_de);
        
        
        array_shift($event_list_en);
        array_shift($event_list_zh);
		
		array_shift($event_list_he);
		array_shift($event_list_ar);
		array_shift($event_list_es);
		array_shift($event_list_pt);
		array_shift($event_list_nl);
		array_shift($event_list_fr);
		array_shift($event_list_de);
		
		

        foreach ($cert_list_en as &$item) {
            $item = $domain . $item;
        }
        foreach ($cert_list_zh as &$item) {
            $item = $domain . $item;
        }
		
		foreach ($cert_list_he as &$item) {
		    $item = $domain . $item;
		}
		foreach ($cert_list_ar as &$item) {
		    $item = $domain . $item;
		}
		foreach ($cert_list_es as &$item) {
		    $item = $domain . $item;
		}
		foreach ($cert_list_pt as &$item) {
		    $item = $domain . $item;
		}
		foreach ($cert_list_nl as &$item) {
		    $item = $domain . $item;
		}
		foreach ($cert_list_fr as &$item) {
		    $item = $domain . $item;
		}
		foreach ($cert_list_de as &$item) {
		    $item = $domain . $item;
		}
		
		

        foreach ($event_list_en as &$item) {
            $item = $domain . $item;
        }
        foreach ($event_list_zh as &$item) {
            $item = $domain . $item;
        }
		
		foreach ($event_list_he as &$item) {
		    $item = $domain . $item;
		}
		foreach ($event_list_ar as &$item) {
		    $item = $domain . $item;
		}
		foreach ($event_list_es as &$item) {
		    $item = $domain . $item;
		}
		foreach ($event_list_pt as &$item) {
		    $item = $domain . $item;
		}
		foreach ($event_list_nl as &$item) {
		    $item = $domain . $item;
		}
		foreach ($event_list_fr as &$item) {
		    $item = $domain . $item;
		}
		foreach ($event_list_de as &$item) {
		    $item = $domain . $item;
		}
		

        if (empty($this->config['system_home']['pop_zh']))
            $pop_zh = '';
        else
            $pop_zh = $domain . $this->config['system_home']['pop_zh'];
			
        if (empty($this->config['system_home']['pop_en']))
            $pop_en = '';
        else
            $pop_en = $domain . $this->config['system_home']['pop_en'];
			
        if (empty($this->config['system_home']['pop_he']))
            $pop_he = '';
        else
            $pop_he = $domain . $this->config['system_home']['pop_he'];
			
        if (empty($this->config['system_home']['pop_ar']))
            $pop_ar = '';
        else
            $pop_ar = $domain . $this->config['system_home']['pop_ar'];
		
		if (empty($this->config['system_home']['pop_es']))
		    $pop_es = '';
		else
		    $pop_es = $domain . $this->config['system_home']['pop_es'];
			
		if (empty($this->config['system_home']['pop_pt']))
		    $pop_pt = '';
		else
		    $pop_pt = $domain . $this->config['system_home']['pop_pt'];
			
		if (empty($this->config['system_home']['pop_nl']))
		    $pop_nl = '';
		else
		    $pop_nl = $domain . $this->config['system_home']['pop_nl'];
			
		if (empty($this->config['system_home']['pop_fr']))
		    $pop_fr = '';
		else
		    $pop_fr = $domain . $this->config['system_home']['pop_fr'];
			
		if (empty($this->config['system_home']['pop_de']))
		    $pop_de = '';
		else
		    $pop_de = $domain . $this->config['system_home']['pop_de'];
			

			
			
			
			
			
        if (empty($this->config['system_basic']['logo']))
            $logo = '';
        else
            $logo = $domain . $this->config['system_basic']['logo'];
			
			
			
        $system_info2 = Configure::find(6);
        $info2 = json_decode($system_info2['content'], true);
        $data['config'] = [
            'operation_time' => ($this->config['system_basic']['open_time'] ?? '') . ' - ' . ($this->config['system_basic']['close_time'] ?? ''),
            'withdraw_time' => ($this->config['system_basic']['with_open_time'] ?? '') . ' - ' . ($this->config['system_basic']['with_close_time'] ?? ''),
            'app_name' => $this->config['system_basic']['app_name'] ?? '',
            'app_name2' => $this->config['system_basic']['app_name2'] ?? '',
            'app_logo' => $logo,

			
            'active_en' => $event_list_en,
            'active_zh' => $event_list_zh,
			
			'active_he' => $event_list_he,
			'active_ar' => $event_list_ar,
			'active_es' => $event_list_es,
			'active_pt' => $event_list_pt,
			'active_nl' => $event_list_nl,
			'active_fr' => $event_list_fr,
			'active_de' => $event_list_de,
			

            'cert_en' => $cert_list_en,
            'cert_zh' => $cert_list_zh,
			
			'cert_he' => $cert_list_he,
			'cert_ar' => $cert_list_ar,
			'cert_es' => $cert_list_es,
			'cert_pt' => $cert_list_pt,
			'cert_nl' => $cert_list_nl,
			'cert_fr' => $cert_list_fr,
			'cert_de' => $cert_list_de,
			
            'event_list' => [
                'en' => $event_list_en,
                'zh' => $event_list_zh,
				
				'he' => $event_list_he,
				'ar' => $event_list_ar,
				'es' => $event_list_es,
				'pt' => $event_list_pt,
				'nl' => $event_list_nl,
				'fr' => $event_list_fr,
				'de' => $event_list_de,

            ],
            'cert' => [
                'en' => $cert_list_en,
                'zh' => $cert_list_zh,
				
				'he' => $cert_list_he,
				'ar' => $cert_list_ar,
				'es' => $cert_list_es,
				'pt' => $cert_list_pt,
				'nl' => $cert_list_nl,
				'fr' => $cert_list_fr,
				'de' => $cert_list_de,

            ],
            'pop_en' => $pop_en,
            'pop_zh' => $pop_zh,
			
			'pop_he' => $pop_he,
			'pop_ar' => $pop_ar,
			'pop_es' => $pop_es,
			'pop_pt' => $pop_pt,
			'pop_nl' => $pop_nl,
			'pop_fr' => $pop_fr,
			'pop_de' => $pop_de,
			
            'pop' => [
                'en' => $pop_en ?? '',
                'zh' => $pop_zh ?? '',
				
				'he' => $pop_he ?? '',
				'ar' => $pop_ar ?? '',
				'es' => $pop_es ?? '',
				'pt' => $pop_pt ?? '',
				'nl' => $pop_nl ?? '',
				'fr' => $pop_fr ?? '',
				'de' => $pop_de ?? '',

            ],

            'tips_en' => $this->config['system_home']['system_tips_en'] ?? '',
            'tips_zh' => $this->config['system_home']['system_tips_zh'] ?? '',
			
			'tips_he' => $this->config['system_home']['system_tips_he'] ?? '',
			'tips_ar' => $this->config['system_home']['system_tips_ar'] ?? '',
			'tips_es' => $this->config['system_home']['system_tips_es'] ?? '',
			'tips_pt' => $this->config['system_home']['system_tips_pt'] ?? '',
			'tips_nl' => $this->config['system_home']['system_tips_nl'] ?? '',
			'tips_fr' => $this->config['system_home']['system_tips_fr'] ?? '',
			'tips_de' => $this->config['system_home']['system_tips_de'] ?? '',
			

            'intro_en' => $this->config['system_home']['intro_en'] ?? '',
            'intro_zh' => $this->config['system_home']['intro_zh'] ?? '',
			
			'intro_he' => $this->config['system_home']['intro_he'] ?? '',
			'intro_ar' => $this->config['system_home']['intro_ar'] ?? '',
			'intro_es' => $this->config['system_home']['intro_es'] ?? '',
			'intro_pt' => $this->config['system_home']['intro_pt'] ?? '',
			'intro_nl' => $this->config['system_home']['intro_nl'] ?? '',
			'intro_fr' => $this->config['system_home']['intro_fr'] ?? '',
			'intro_de' => $this->config['system_home']['intro_de'] ?? '',
			
            'intro' => [
                'en' => $this->config['system_home']['intro_en'] ?? '',
                'zh' => $this->config['system_home']['intro_zh'] ?? '',
				
				'he' => $this->config['system_home']['intro_he'] ?? '',
				'ar' => $this->config['system_home']['intro_ar'] ?? '',
				'es' => $this->config['system_home']['intro_es'] ?? '',
				'pt' => $this->config['system_home']['intro_pt'] ?? '',
				'nl' => $this->config['system_home']['intro_nl'] ?? '',
				'fr' => $this->config['system_home']['intro_fr'] ?? '',
				'de' => $this->config['system_home']['intro_de'] ?? '',

            ],

            'rule_en' => $this->config['system_home']['rule_en'] ?? '',
            'rule_zh' => $this->config['system_home']['rule_zh'] ?? '',
			
			'rule_he' => $this->config['system_home']['rule_he'] ?? '',
			'rule_ar' => $this->config['system_home']['rule_ar'] ?? '',
			'rule_es' => $this->config['system_home']['rule_es'] ?? '',
			'rule_pt' => $this->config['system_home']['rule_pt'] ?? '',
			'rule_nl' => $this->config['system_home']['rule_nl'] ?? '',
			'rule_fr' => $this->config['system_home']['rule_fr'] ?? '',
			'rule_de' => $this->config['system_home']['rule_de'] ?? '',
			
            'rule' => [
                'en' => $this->config['system_home']['rule_en'] ?? '',
                'zh' => $this->config['system_home']['rule_zh'] ?? '',
				
				'he' => $this->config['system_home']['rule_he'] ?? '',
				'ar' => $this->config['system_home']['rule_ar'] ?? '',
				'es' => $this->config['system_home']['rule_es'] ?? '',
				'pt' => $this->config['system_home']['rule_pt'] ?? '',
				'nl' => $this->config['system_home']['rule_nl'] ?? '',
				'fr' => $this->config['system_home']['rule_fr'] ?? '',
				'de' => $this->config['system_home']['rule_de'] ?? '',

            ],

            'about_us_en' => $this->config['system_home']['about_us_en'] ?? '',
            'about_us_zh' => $this->config['system_home']['about_us_zh'] ?? '',
			
			'about_us_he' => $this->config['system_home']['about_us_he'] ?? '',
			'about_us_ar' => $this->config['system_home']['about_us_ar'] ?? '',
			'about_us_es' => $this->config['system_home']['about_us_es'] ?? '',
			'about_us_pt' => $this->config['system_home']['about_us_pt'] ?? '',
			'about_us_nl' => $this->config['system_home']['about_us_nl'] ?? '',
			'about_us_fr' => $this->config['system_home']['about_us_fr'] ?? '',
			'about_us_de' => $this->config['system_home']['about_us_de'] ?? '',
			
            'about_us' => [
				'en' => $this->config['system_home']['about_us_en'] ?? '',
                'zh' => $this->config['system_home']['about_us_zh'] ?? '',
				
				'he' => $this->config['system_home']['about_us_he'] ?? '',
				'ar' => $this->config['system_home']['about_us_ar'] ?? '',
				'es' => $this->config['system_home']['about_us_es'] ?? '',
				'pt' => $this->config['system_home']['about_us_pt'] ?? '',
				'nl' => $this->config['system_home']['about_us_nl'] ?? '',
				'fr' => $this->config['system_home']['about_us_fr'] ?? '',
				'de' => $this->config['system_home']['about_us_de'] ?? '',

            ],
            'our_services' => [
				'en' => $this->config['system_home']['our_services_en'] ?? '',
                'zh' => $this->config['system_home']['our_services_zh'] ?? '',
				
				'he' => $this->config['system_home']['our_services_he'] ?? '',
				'ar' => $this->config['system_home']['our_services_ar'] ?? '',
				'es' => $this->config['system_home']['our_services_es'] ?? '',
				'pt' => $this->config['system_home']['our_services_pt'] ?? '',
				'nl' => $this->config['system_home']['our_services_nl'] ?? '',
				'fr' => $this->config['system_home']['our_services_fr'] ?? '',
				'de' => $this->config['system_home']['our_services_de'] ?? '',

            ],
            'tips' => $info2,
            'index_img_hotel_title' => $domain . '/uploads/index-img-hotel-title.png',
        ];
        if ($uid) {
            $data['vip_list'] = Level::select()->toArray();
            foreach ($data['vip_list'] as &$item) {
                if ($item['img'])
                    $item['img'] = $domain . $item['img'];
                $item['withdraw_rate_txt'] = ($item['withdraw_rate'] * 100) . '%';
                $item['order_rate_txt'] = ($item['order_rate'] * 100) . '%';
            }
        }
        $data['register_email_required'] = config('user.reg_email_required'); //注册邮箱是否必填

        $this->result(true, '操作成功', $data);
    }

    //获取客服列表
    public function getCustomer()
    {
        $list = $this->config['system_link'];
        $returnData = [];
        $serviceList = [1, 2, 3, 4];
        foreach ($list as $key => $value) {
            if (in_array($value['name'], $serviceList)) {
                if ($value['name'] == 1) {
                    $returnData['kefu'] = $value['val'];
                }
                $returnData['kefu' . $value['name']] = $value['val'];
            }
        }
        $returnData['list'] = $list;
        $this->result(true, '操作成功', $returnData);
    }

    //获取VIP列表
    public function getVipList()
    {
        $domain = $this->request->domain();
        $list = Level::select()->toArray();
        foreach ($list as &$item) {
            if ($item['img'])
                $item['img'] = $domain . $item['img'];
            $item['withdraw_rate_txt'] = ($item['withdraw_rate'] * 100) . '%';
            $item['order_rate_txt'] = ($item['order_rate'] * 100) . '%';
            $item['show_rate_text'] = $item['order_rate'] . '';
        }
        $this->result(true, '操作成功', $list);
    }

    //购买会员
    public function buyVip()
    {
        $level_id = $this->request->post('level_id', 0, 'intval');
        if (!$level_id)
            $this->result(false, '参数有误');
        $uid = $this->request->user['id'];
        $user_info = User::field('level_id,balance')->find($uid);
        if (!$user_info)
            $this->result(false, '数据不存在');
        $level_info = Level::field('price,reward_price')->find($level_id);
        if (!$level_info)
            $this->result(false, '数据不存在');
        if ($user_info['level_id'] == $level_id)
            $this->result(false, '您已经是这个等级了');
        if ($user_info['level_id'] > $level_id)
            $this->result(false, '不可降级');
        if ($user_info['balance'] < $level_info['price'])
            $this->result(false, '可用余额不足');
        Db::startTrans();
        try {
            $data['balance'] = $user_info['balance'] - $level_info['price'] + $level_info['reward_price'];
            $data['level_id'] = $level_id;
            User::update($data, ['id' => $uid]);
            //添加记录
            $log[] = [
                'uid' => $uid,
                'type' => 6,
                'status' => 2,
                'price' => $level_info['price'],
                'price_pre' => $user_info['balance'],
                'explain' => '会员升级到VIP-' . $level_id,
                'create_time' => time(),
                'extra_id' => $level_id,
            ];
            if ($level_info['reward_price'] > 0) {
                $log[] = [
                    'uid' => $uid,
                    'type' => 7,
                    'status' => 1,
                    'price' => $level_info['reward_price'],
                    'price_pre' => $user_info['balance'] - $level_info['price'],
                    'explain' => '会员升级奖励VIP-' . $level_id,
                    'create_time' => time(),
                    'extra_id' => $level_id,
                ];
            }
            WalletLog::insertAll($log);
            Db::commit();
        } catch (\Exception $e) {
            Log::error('会员升级失败-' . $e->getMessage() . ":" . $e->getLine());
            Db::rollback();
            $this->result(false, '系统繁忙，请重试');
        }
        $this->result(true);
    }

    //获取消息列表
    public function getMesList()
    {
        $uid = $this->request->user['id'] ?? 0;
        $web_time = $this->request->post('web_time', '', 'trim');
        if ($web_time)
            $diff_time = time() - strtotime($web_time);
        else
            $diff_time = 0;
        $page = $this->request->post('page', 1, 'intval');
        $list = Message::where(['uid' => $uid])->order('id desc')->paginate(['list_rows' => 10, 'page' => $page])->toArray();
        foreach ($list['data'] as &$item) {
            $item['create_time'] = date('Y-m-d H:i:s', strtotime($item['create_time']) - $diff_time);
        }
        $this->result(true, '操作成功', $list['data'], ['total' => $list['total'], 'last_page' => $list['last_page']]);
    }

    //设置消息已读
    public function setMesRead()
    {
        $id = $this->request->post('mes_id', 0, 'intval');
        if (!$id)
            $this->result(false, '参数有误');
        Message::update(['status' => 1], ['id' => $id]);
        $this->result(true);
    }

    //获取app滚动列表
    public function getAppList()
    {
        $domain = $this->request->domain();
        $list = AppRoll::field('id,name,img')->select()->toArray();
        foreach ($list as &$item) {
            if ($item['img'])
                $item['img'] = $domain . $item['img'];
        }
        $this->result(true, '操作成功', $list);
    }

    //获取app滚动详情
    public function getAppInfo()
    {
        $id = $this->request->post('id', 0, 'intval');
        $web_time = $this->request->post('web_time', '', 'trim');
        if ($web_time)
            $diff_time = time() - strtotime($web_time);
        else
            $diff_time = 0;
        if (!$id)
            $this->result(false, '参数有误');
        $info = AppRoll::find($id);
        if (!$info)
            $this->result(false, '数据不存在');
        $info = $info->toArray();
        $info['create_time'] = date('Y-m-d H:i:s', strtotime($info['create_time']) - $diff_time);
        $this->result(true, '操作成功', $info);
    }

    public function getAgreement()
    {
        $sys_info = Configure::find(5);
        $info = json_decode($sys_info['content'], true);
        if (!$info) {
            $info = ['register_vi' => '', 'register_en' => ''];
        }
        $info['register_agree'] = $info['register_en'] ?? '';
        $this->result(true, '操作成功', $info);
    }

}
