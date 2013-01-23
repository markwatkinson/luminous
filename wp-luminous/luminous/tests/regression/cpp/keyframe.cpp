#include <vector>
#include <map>

#include <assimp.hpp>
#include <aiPostProcess.h>
#include <aiScene.h>
#include <aiAnim.h>

#include "keyframe.h"

namespace Animator
{

    KeyFrameController::KeyFrameController(const aiScene *
                                           model_):standstill(false),
        root_for_standstill(NULL), num_animations(0)
    {
        // num_animations = 0;
        model = model_;
        FinalizeFrame();
    }

    void KeyFrameController::NewFrame(void)
    {
        global_node_positions.clear();
        local_node_positions.clear();
        node_transforms.clear();
        node_weightings.clear();
        // weightings.clear();
        num_animations = 0;
        node_weightings_total.clear();
    }

    void KeyFrameController::FinalizeFrame(void)
    {
        aiMatrix4x4 identity;

        CalcLocalPositions(model->mRootNode);
        CalcGlobalPositions(model->mRootNode, root_modifier);
    }

    aiMatrix4x4 KeyFrameController::GetLocalPosition(const char *nodename)
    {
        return local_node_positions[(std::string) nodename];
    }

    const aiMatrix4x4 &
        KeyFrameController::GetGlobalPosition(const char *nodename)
    {
        std::string name = nodename;
        const aiMatrix4x4 & m = global_node_positions[name];

        return m;
    }

    aiMatrix4x4 KeyFrameController::CalcNodeLocalPosition(const aiNode * node)
    {

        std::string name = node->mName.data;
        size_t num_transforms = node_transforms.size();

        struct animation_data *data =
            new struct animation_data[num_transforms];

        for (unsigned int i = 0; i < node_transforms.size(); i++)
        {
            std::map < std::string, struct animation_data >&nodes =
                node_transforms[i];

            if (nodes.count(name) > 0)
            {
                data[i] = nodes[name];
            }
            else
            {
                aiVector3D translation;
                aiVector3D scaling;
                aiQuaternion rotation;
                const aiMatrix4x4 & m = node->mTransformation;

                m.Decompose(scaling, rotation, translation);

                data[i].translation = translation;
                data[i].scale = scaling;
                data[i].rotation = rotation;
            }
        }

        // FIXME: below doesn't correctly handle a set of zero-ed weightings.

        // float cumulative_weighting = weightings[0];
        float cumulative_weighting = GetNodeWeighting(0, node->mName.data);
        aiVector3D t = data[0].translation;
        aiVector3D s = data[0].scale;
        aiQuaternion r = data[0].rotation;
        Assimp::Interpolator < aiQuaternion > interp_q;
        Assimp::Interpolator < aiVector3D > interp_v;

        for (unsigned int i = 1; i < node_transforms.size(); i++)
        {
            float weighting = GetNodeWeighting(i, node->mName.data);    // weightings[i];
            const aiVector3D & translation = data[i].translation;
            const aiQuaternion & rotation = data[i].rotation;
            const aiVector3D & scale = data[i].scale;
            float w = 1.0f - weighting;

            interp_v(t, translation, t, w);
            interp_v(s, scale, s, w);
            // t += translation * w;
            // s += scale * w;
            interp_q(r, rotation, r, w);
            cumulative_weighting += weighting;
        }

        // float recip = (num_transforms == 0)? 1: 1.0f/num_transforms;
        // t *= recip;
        // s *= recip;
        
        aiMatrix4x4 r_matrix(r.GetMatrix());
        aiMatrix4x4 s_matrix;
        aiMatrix4x4 t_matrix;
        aiMatrix4x4::Scaling(s, s_matrix);
        aiMatrix4x4::Translation(t, t_matrix);

        aiMatrix4x4 transform = (t_matrix * r_matrix * s_matrix);

        delete[]data;
        return transform;
    }


    void KeyFrameController::CalcLocalPositions(const aiNode * node)
    {
        std::string nodename = node->mName.data;
        aiMatrix4x4 transform;

        if (node_transforms.size() == 0)
            transform = node->mTransformation;
        else
            transform = CalcNodeLocalPosition(node);


        local_node_positions[nodename] = transform;

        for (unsigned int i = 0; i < node->mNumChildren; i++)
            CalcLocalPositions(node->mChildren[i]);
    }

    void KeyFrameController::CalcGlobalPositions(const aiNode * node,
                                                 const aiMatrix4x4 &
                                                 parent_matrix)
    {
        const std::string & nodename = node->mName.data;
        aiMatrix4x4 m(parent_matrix);
        m *= GetLocalPosition(node->mName.data);
        global_node_positions[nodename] = m;


        for (unsigned int i = 0; i < node->mNumChildren; i++)
            CalcGlobalPositions(node->mChildren[i], m);
    }


    void KeyFrameController::SetNodeWeighting(size_t animation_id,
                                              const char *nodename,
                                              float weighting, bool recursive)
    {
        if (node_weightings.size() <= animation_id)
            // return;
            node_weightings.resize(animation_id + 1);

        if (recursive)
        {
            aiNode *node = model->mRootNode->FindNode(nodename);
            SetNodeWeighting(animation_id, node, weighting, true);
        }
        else
        {
            std::map < std::string, float >&m = node_weightings[animation_id];
            m[nodename] = weighting;
        }
    }

    void KeyFrameController::SetNodeWeighting(size_t animation_id,
                                              const aiNode * node,
                                              float weighting, bool recursive)
    {
        SetNodeWeighting(animation_id, node->mName.data, weighting);
        
        if (recursive)
        {
            for (unsigned int i = 0; i < node->mNumChildren; i++)
                SetNodeWeighting(animation_id, node->mChildren[i], weighting,
                                 recursive);
        }
    }




    float
        KeyFrameController::GetNodeWeighting(size_t animation_index,
                                             const char *nodename)
    {


        float weighting = node_weightings[animation_index][nodename];

        // std::map<std::string, float>::const_iterator it =
        // node_weightings_total.find(nodename);
        // if (it != node_weightings_total.end())
        // {
        // return weighting / it->second;
        // }

        float total = 0;

        for (unsigned int i = 0; i < num_animations; i++)
        {
            total += node_weightings[i][nodename];
        }

        if (!total)
            total = 1.0f;

        // node_weightings_total.insert(std::pair<std::string,
        // float>(nodename, total));

        return weighting / total;
    }



    // TODO:
    // this function is long and confusing. 
    // shoiuld at least split it up into a bunch of short and confusing
    // functions instead.
    // also this function is the biggest bottleneck in this class so get
    // optimising

    size_t
        KeyFrameController::PlayAnimation(const aiAnimation * anim,
                                          float weighting, float time)
    {
        // Exception?
        if (anim == NULL)
            return 0;

        num_animations++;

        // weightings.resize(num_animations);
        // weightings[num_animations-1] = weighting;


        // testing only.
        // double duration = anim->mDuration;
        // while (time > duration)
        // time-= duration;

        aiVector3D scaling(1, 1, 1);

        // double this_frame_time_scaling;
        // double next_frame_time_scaling;


        aiQuatKey last_rotation;
        aiQuatKey next_rotation;
        aiVectorKey last_translation;
        aiVectorKey next_translation;
        Assimp::Interpolator < aiQuatKey > interp_q;
        Assimp::Interpolator < aiVectorKey > interp_v;
        aiQuaternion rotation;
        aiVector3D translation;

        for (unsigned int i = 0; i < anim->mNumChannels; i++)
        {
            aiNodeAnim *channel = anim->mChannels[i];
            
            scaling.x = 1;
            scaling.y = 1;
            scaling.z = 1;

            if (channel->mNumScalingKeys > 0)
            {
                // scaling
                unsigned int s_index;
                bool found = false;

                for (s_index = 0; s_index < channel->mNumScalingKeys - 1;
                     s_index++)
                {
                    if (time < channel->mScalingKeys[s_index + 1].mTime)
                    {
                        found = true;
                        break;
                    }
                }
                if (found)
                {
                    if (s_index < channel->mNumScalingKeys)
                        scaling = channel->mScalingKeys[s_index].mValue;
                }
            }
            
            // rotation 
            
            // just to stop warnings about uninitialised data, but they
            // wouldn't be used anyway.
            last_rotation.mTime = 0;
            next_rotation.mTime = 0;

            bool interp_rotation = true;

            if (channel->mNumRotationKeys > 1)
            {
                unsigned int r_index;
                bool found = false;
                
                for (r_index = 0; r_index < channel->mNumRotationKeys - 1;
                     r_index++)
                {
                    if (time < channel->mRotationKeys[r_index + 1].mTime)
                    {
                        found = true;
                        break;
                    }
                }
                if (found)
                {
                    last_rotation = channel->mRotationKeys[r_index];
                    next_rotation = channel->mRotationKeys[r_index + 1];
                }
                else
                {
                    last_rotation =
                        channel->mRotationKeys[channel->mNumRotationKeys - 1];
                    interp_rotation = false;
                }

            }
            else
                interp_rotation = false;

            // translation 
            last_translation.mTime = 0;
            next_translation.mTime = 0;

            bool interp_translation = true;

            if (channel->mNumPositionKeys > 1)
            {
                unsigned int t_index;

                bool found = false;

                for (t_index = 0; t_index < channel->mNumPositionKeys - 1;
                     t_index++)
                {
                    if (time < channel->mPositionKeys[t_index + 1].mTime)
                    {
                        found = true;
                        break;
                    }
                }
                if (found)
                {
                    last_translation = channel->mPositionKeys[t_index];
                    next_translation = channel->mPositionKeys[t_index + 1];
                }
                else
                {
                    last_translation =
                        channel->mPositionKeys[channel->mNumPositionKeys - 1];
                    interp_translation = false;
                }
            }
            else
                interp_translation = false;

            // the docs aren't clear on how the time input to the interp func
            // works but I think it should be normalised
            // to [0, 1] even in the case of giving it a Vectorkey/Quatkey
            // it seems like the library should normalise it itself if it's
            // taken in a key (as they have)
            // their time inbuilt; so this might break in future if the
            // library is changed to do that.  

            if (interp_rotation)
            {
                double last_r_time = last_rotation.mTime;
                double next_r_time = next_rotation.mTime;
                double r_time_normalised =
                    (time - last_r_time) / (next_r_time - last_r_time);
                interp_q(rotation, last_rotation, next_rotation,
                         r_time_normalised);
            }
            else
                rotation = last_rotation.mValue;

            if (interp_translation)
            {
                double last_t_time = last_translation.mTime;
                double next_t_time = next_translation.mTime;
                double t_time_normalised =
                    (time - last_t_time) / (next_t_time - last_t_time);
                interp_v(translation, last_translation, next_translation,
                         t_time_normalised);
            }
            else
                translation = last_translation.mValue;

            // this is a really horrible hack to enforce that the DOOM3 models 
            // don't move
            // the problem is that every other model type I've seen keeps the
            // model in its own
            // little space and walking animations don't move it,
            // but the doom 3 ones actually translate along the axes when they 
            // walk.
            // HOWEVER, the DOOM3 ones have the most rich animations I've been 
            // able to find 
            // in a model format that works well with the library.
            // perhaps the best way to do this would be to move the axis along 
            // with them or something?

            if (standstill &&
                root_for_standstill != NULL &&
                !strcmp(root_for_standstill->mName.data,
                        channel->mNodeName.data))
            {
                aiNode *root = model->mRootNode;
                aiVector3D s, t;
                aiQuaternion r;

                root->mTransformation.Decompose(s, r, t);
                translation.Set(t.x, t.y, t.z);
            }
            struct animation_data data =
                BuildTransformData(scaling, rotation, translation);


            AddNodeTransform(channel->mNodeName.data, data,
                             num_animations - 1);
            SetNodeWeighting(num_animations - 1, channel->mNodeName.data,
                             weighting);
        }

        return num_animations - 1;


    }

    void KeyFrameController::AddNodeTransform(const char *nodename,
                                              const struct animation_data
                                              &data, size_t index)
    {
        if (index >= node_transforms.size())
            node_transforms.resize(index + 1);
        std::map < std::string, struct animation_data >&nodes =
            node_transforms[index];
        nodes[nodename] = data;
    }


    inline struct animation_data
        KeyFrameController::BuildTransformData(const aiVector3D & scaling,
                                               const aiQuaternion & rotation,
                                               const aiVector3D & translation)
        const
    {
        struct animation_data data;
        aiMatrix4x4 & transformation_matrix = data.transform;
        aiMatrix4x4 scaling_matrix;
        aiMatrix4x4 t_matrix;
        aiMatrix4x4::Scaling(scaling, scaling_matrix);
        aiMatrix4x4 r_matrix = aiMatrix4x4(rotation.GetMatrix());
        aiMatrix4x4::Translation(translation, t_matrix);
        transformation_matrix = t_matrix * r_matrix * scaling_matrix;
        
        data.scale = scaling;
        data.translation = translation;
        data.rotation = rotation;

        return data;
    }
}
