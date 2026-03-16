<?php

class FrmMidigatorPreventionHelper {

    protected FrmMidigatorApi $api;
    protected $preventionModel;
    //protected $preventionHistoryModel;

    public function __construct(FrmMidigatorApi $api=null) {
        $this->api = $api ?? new FrmMidigatorApi();

        $this->preventionModel = new FrmMidigatorPreventionModel();
        //$this->preventionHistoryModel = new FrmMidigatorPreventionHistoryModel();
    }

    public function resolvePreventionAlert(string $preventionGuid, string $resolutionType, string $otherDescription = ''): array {

        // Call API to resolve alert
        $apiRes = $this->api->resolvePreventionAlert($preventionGuid, $resolutionType, $otherDescription);
        if (is_wp_error($apiRes)) {
            return [
                'ok'    => false,
                'error' => 'WP_Error: ' . $apiRes->get_error_message(),
                'code'  => $apiRes->get_error_code(),
                'data'  => $apiRes->get_error_data(),
            ];
        }
    
        // Set status resolved
        $this->preventionModel->setResolved($preventionGuid, true);
    
        // create/update resolve record
        $resolveData = [
            'resolution_type' => $resolutionType,
            'description'     => $otherDescription,
        ];
        $this->preventionModel->createResolve($preventionGuid, $resolveData);
        
        return [
            'ok' => true,
        ];
    }

    public function getPreventionData(string $preventionGuid): array {

        return $this->api->getPreventionData($preventionGuid);

    }

    public function deletePreventionById(int $id): bool {

        // Delete local record
        $this->preventionModel->deleteById($id);
        return true;

    }

    public function deletePreventionsAll( array $filter ): bool {

        // Possible for now: is_resolved=true/false

        // All
        $opts = array_merge( $filter, [
            'per_page' => 100000,
            'page' => 1,
        ] );

        // Get all matching preventions
        $preventions = $this->preventionModel->getList( $filter, $opts );

        $list = $preventions['data'] ?? [];

        // Delete each prevention
        foreach ($list as $prevention) {
            $this->preventionModel->deleteById( $prevention['id'] );
        }

        return true;

    }

}