<?php
/**
 * Created by PhpStorm.
 * User: derek
 * Date: 2019/11/10
 * Time: 22:05
 */

namespace App\Controller\Admin;


use App\Annotation\SysLogAnnotation;
use App\Controller\AbstractController;
use App\Service\CommonService;
use App\Service\Instance\JwtInstance;
use Hyperf\Di\Annotation\Inject;
use Qiniu\Auth;
use Qiniu\Storage\UploadManager;

/**
 * @SysLogAnnotation()
 */
class SysOssController extends AbstractController
{

    /**
     * @Inject()
     * @var CommonService
     */
    protected $commonService;

    /**
     * sys/oss/list
     * OSS列表
     */
    public function sysOssList()
    {
        $currentLoginUserId = JwtInstance::instance()->build()->getId();
        $page = (int)$this->request->input('page');
        $limit = (int)$this->request->input('limit');
        $result = $this->commonService->getSysLogList($limit, $page);

        return $this->response->success([
            'page' => $result
        ]);
    }

    /**
     * sys/oss/delete
     * oss删除
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function sysOssDelete()
    {
        $currentLoginUserId = JwtInstance::instance()->build()->getId();

        $params = $this->request->post();
        if (!is_array($params) || empty($params)) {
            return $this->response->error("提交错误");
        }
        $result = $this->commonService->sysOssDelete($params);
        if ($result) {
            return $this->response->success();
        } else {
            return $this->response->error("删除失败");
        }
    }

    /**
     * 获取配置
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function sysOssConfig()
    {
        $currentLoginUserId = JwtInstance::instance()->build()->getId();
        try {
            $result = $this->commonService->sysOssConfig();
            return $this->response->success(['config' => json_decode($result->param_value, true)]);
        } catch (\Exception $e) {
            return $this->response->error($e->getMessage());
        }
    }

    /**
     * 保存oss配置
     */
    public function sysOssSaveConfig()
    {
        $currentLoginUserId = JwtInstance::instance()->build()->getId();
        $type = (int)$this->request->input('type');
        $aliyunAccessKeyId = (string)$this->request->input('aliyunAccessKeyId');
        $aliyunAccessKeySecret = (string)$this->request->input('aliyunAccessKeySecret');
        $aliyunBucketName = (string)$this->request->input('aliyunBucketName');

        $aliyunDomain = (string)$this->request->input('aliyunDomain');
        $aliyunEndPoint = (string)$this->request->input('aliyunEndPoint');
        $aliyunPrefix = (string)$this->request->input('aliyunPrefix');
        $qcloudBucketName = (string)$this->request->input('qcloudBucketName');
        $qcloudDomain = (string)$this->request->input('qcloudDomain');
        $qcloudPrefix = (string)$this->request->input('qcloudPrefix');
        $qcloudSecretId = (string)$this->request->input('qcloudSecretId');
        $qcloudSecretKey = (string)$this->request->input('qcloudSecretKey');
        $qiniuAccessKey = (string)$this->request->input('qiniuAccessKey');
        $qiniuBucketName = (string)$this->request->input('qiniuBucketName');
        $qiniuDomain = (string)$this->request->input('qiniuDomain');
        $qiniuPrefix = (string)$this->request->input('qiniuPrefix');
        $qiniuSecretKey = (string)$this->request->input('qiniuSecretKey');

        $params = [
            'type' => $type,
            'aliyunAccessKeyId' => $aliyunAccessKeyId,
            'aliyunAccessKeySecret' => $aliyunAccessKeySecret,
            'aliyunBucketName' => $aliyunBucketName,
            'aliyunDomain' => $aliyunDomain,
            'aliyunEndPoint' => $aliyunEndPoint,
            'aliyunPrefix' => $aliyunPrefix,
            'qcloudBucketName' => $qcloudBucketName,
            'qcloudDomain' => $qcloudDomain,
            'qcloudPrefix' => $qcloudPrefix,
            'qcloudSecretId' => $qcloudSecretId,
            'qcloudSecretKey' => $qcloudSecretKey,
            'qiniuAccessKey' => $qiniuAccessKey,
            'qiniuBucketName' => $qiniuBucketName,
            'qiniuDomain' => $qiniuDomain,
            'qiniuPrefix' => $qiniuPrefix,
            'qiniuSecretKey' => $qiniuSecretKey
        ];

        $result = $this->commonService->sysOssSaveConfig($params);
        if ($result) {
            return $this->response->success();
        } else {
            return $this->response->error("保存失败");
        }
    }

    /**
     * oss上传文件
     */
    public function sysOssUpload()
    {
        $currentLoginUserId = JwtInstance::instance()->build()->getId();

        $result = $this->commonService->sysOssConfig();
        $config = json_decode($result->param_value, true);

        if(empty($config['qiniuAccessKey'])){
            return $this->response->error("请设置七牛云的oss配置");
        }

        $file = $this->request->file('file');

        $result = false;

        //七牛云
        if (!empty($config) && $config['type'] == 1) {

            $fileName = "/hyperf-skeleton/upload/" . $file->getClientFilename();
            $file->moveTo($fileName);

            $auth = new Auth($config['qiniuAccessKey'], $config['qiniuSecretKey']);
            $uploadToken = $auth->uploadToken($config['qiniuBucketName']);
            $upload_mgr = new UploadManager();
            $rel = $upload_mgr->putFile($uploadToken, $config['qiniuPrefix'] . "/" . $file->getClientFilename(), $fileName);

            if(empty($rel[0])){
                return $this->response->error("请检查oss配置");
            }

            if (!empty($rel) && file_exists($fileName)) {
                unlink($fileName);
            }
            $url = $config['qiniuDomain'] . "/" . $rel[0]['key'];

            $data = [
                'url' => $url,
                'create_date' => date("Y-m-d h:i:s",time())
            ];
            $result = $this->commonService->sysOssSave($data);
        }

        if ($result) {
            return $this->response->success();
        } else {
            return $this->response->error("上传失败");
        }
    }
}