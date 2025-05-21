<?php

namespace App\Services;

use App\Models\AccountOAuth2;
use App\Models\SeleniumSession;
use App\Models\UserCookie;
use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Cookie;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\WebDriverBy;
use Facebook\WebDriver\WebDriverExpectedCondition;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class SeleniumService
{
    protected RemoteWebDriver $driver;
    protected ?SeleniumSession $session;
    protected ?string $baseUrl = null;
    protected string $username;
    protected string $password;

    /**
     * Инициализация Selenium-сессии
     *
     * @throws Exception
     */
    public function init(int $account_id)
    {
        Log::info("before init");

        // Получаем свободный узел для данного baseUrl или с существующей сессией
        $this->session = SeleniumSession::where('account_id', $account_id)
            ->where(function ($query) {
                $query->where('status', SeleniumSession::STATUS_FREE)
                    ->orWhereNotNull('webdriver_session')
                ;
            })
            ->inRandomOrder()
            ->first();

//        dd($this->session);

        Log::info("before \$this->session");
        if (!$this->session) {
            throw new Exception("Нет доступных узлов Selenium для {$account_id}");
        }

        // Если нет сессии или старая не работает — создаем новую
        $this->session->update(['status' => SeleniumSession::STATUS_BUZY]);

        $host = env('SELENIUM_API_URL') . ":4444/wd/hub";
        if ($this->session->webdriver_session) {
            Log::info("if \$this->session->webdriver_session");
            try {
                Log::info("Пытаемся использовать существующую сессию: {$this->session->webdriver_session}");
                $this->driver = RemoteWebDriver::createBySessionID($this->session->webdriver_session, $host, 5000);
                return;
            } catch (Exception $e) {
                Log::warning("Не удалось использовать существующую сессию: " . $e->getMessage());
                $this->session->update(['webdriver_session' => null, 'status' => SeleniumSession::STATUS_FREE]);
            }
        }

        $profileDir = '/tmp/chrome_profile_' . md5($account_id . time());  // Разделяем профили по account_id
        $options = (new ChromeOptions())->addArguments([
            '--disable-gpu',
            '--disable-dev-shm-usage',
            '--remote-debugging-port=0',
            '--headless',
            '--no-sandbox',
            '--disable-blink-features=RootLayerScrolling',
            '--user-data-dir=' . $profileDir
        ]);

        $capabilities = DesiredCapabilities::chrome();
        $capabilities->setCapability(ChromeOptions::CAPABILITY, $options);

        try {
            Log::info("Создаем новую WebDriver-сессию");
            $this->driver = RemoteWebDriver::create($host, $capabilities, 5000);
        } catch (Exception $e) {
            Log::error("Ошибка при создании WebDriver: " . $e->getMessage());
            $this->session->update(['status' => SeleniumSession::STATUS_ERROR]);
            throw $e;
        }

        $timeouts = $this->driver->manage()->timeouts();
        $timeouts->implicitlyWait(10); // Ожидание перед поиском элемента (в секундах)
        $timeouts->pageLoadTimeout(60); // Ожидание загрузки страницы
        $timeouts->setScriptTimeout(30); // Ожидание выполнения скрипта

        // Сохраняем новую сессию
        $webdriverSessionId = $this->driver->getSessionID();
        $this->session->update(['webdriver_session' => $webdriverSessionId, 'account_id' => $account_id]);

        Log::info("after init");
    }

    /**
     * Деструктор освобождает сессию Selenium
     */
    public function __destruct()
    {
        if (isset($this->session)) {
            $this->session?->update(['status' => SeleniumSession::STATUS_FREE]);
        }
    }

    /**
     * Получает cookies пользователя
     */
    private function getCookies($userId)
    {
        return UserCookie::where('user_id', $userId)->get();
    }

    /**
     * Устанавливает учетные данные для авторизации
     *
     * @param string $username Имя пользователя
     * @param string $password Пароль
     * @return self
     */
    public function setCredentials(string $username, string $password): self
    {
        $this->username = $username;
        $this->password = $password;
        return $this;
    }

    /**
     * Устанавливает базовый URL
     *
     * @param string $url Базовый URL
     * @return self
     */
    public function setBaseUrl(string $url): self
    {
        $this->baseUrl = $url;
        return $this;
    }

    /**
     * Применяет cookies в браузере Selenium
     */
    private function applyCookies($cookies)
    {
        foreach ($cookies as $cookie) {
            $this->driver->manage()
                ->addCookie(Cookie::createFromArray($cookie->toArray()));
        }
        sleep(2);
        $this->driver->navigate()->refresh();
    }

    /**
     * Авторизация с использованием OAuth2-токена
     */
    public function loginWithToken($userId)
    {
        $domain = parse_url($this->baseUrl)['host'];
        $account = AccountOAuth2::query()->where('domain', $domain)->first();
        if ($account->isTokenExpired()) {
            $account->refreshAccessData();
        }

        $cookies = $this->getCookies($userId);
        if ($cookies->isEmpty()) {
            return $this->login($userId);
        }

        foreach ($cookies as $cookie) {
            if ($cookie->name == 'last_login') {
                $cookie->value = '';
            } elseif ($cookie->name == 'access_token') {
                $cookie->value = $account->access_token;
            } elseif ($cookie->name == 'refresh_token') {
                $cookie->value = $account->refresh_token;
            }
        }

        $this->driver->get($this->baseUrl);
        $this->applyCookies($cookies);
        sleep(5);

        return empty($this->driver->findElements(WebDriverBy::id('authentication')))
            ? ['status' => 'logged_in_with_token']
            : $this->login($userId);
    }

    /**
     * Авторизация с сохранившихся кук
     */
    public function loginWithCookies($userId)
    {
        $cookies = $this->getCookies($userId);
        if ($cookies->isEmpty()) {
            return $this->login($userId);
        }

        $this->driver->get($this->baseUrl);
        $this->applyCookies($cookies);
        sleep(5);

        return empty($this->driver->findElements(WebDriverBy::id('authentication')))
            ? ['status' => 'logged_in_with_cookies']
            : $this->login($userId);
    }

    /**
     * Авторизация с использованием логина и пароля
     */
    private function login($userId)
    {
        Log::info("login");
        $this->driver->get($this->baseUrl);
        $this->driver->wait(10)->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::name('username')));

        $this->driver->findElement(WebDriverBy::name('username'))->sendKeys($this->username);
        $this->driver->findElement(WebDriverBy::name('password'))->sendKeys($this->password);
        $this->driver->findElement(WebDriverBy::cssSelector('button[type="submit"]'))->click();
        sleep(2);

        foreach ($this->driver->manage()->getCookies() as $cookie) {
            UserCookie::updateOrCreate([
                'user_id' => $userId,
                'name' => $cookie->getName()
            ], $cookie->toArray());
        }

        return ['status' => 'logged_in'];
    }

    /**
     * Отправляет сообщение в лид
     */
    public function sendLeadMessage(int $lead_id, string $text)
    {
        $this->driver->get("{$this->baseUrl}/leads/detail/{$lead_id}");
        $this->driver->wait(10)
            ->until(WebDriverExpectedCondition::urlContains('leads/detail'));

        $this->driver->executeScript("$('[data-id=\"chat\"]').click()");
        $this->driver->wait(10)
            ->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::className('feed-compose-user__name')));
        $this->driver->executeScript("$('.feed-compose-user__name').click()");
        $this->driver->wait(25)
            ->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::className('multisuggest__suggest-item')));
        $this->driver->executeScript("document.querySelector('.multisuggest__suggest-item').click();");
        $this->driver->wait(10)
            ->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::className('feed-compose__message')));
        $this->driver->findElement(WebDriverBy::className('feed-compose__message'))->sendKeys($text);
        $this->driver->wait(10)
            ->until(WebDriverExpectedCondition::presenceOfElementLocated(WebDriverBy::className('feed-note__button')));
        $this->driver->findElement(WebDriverBy::className('feed-note__button'))->click();
    }
}
